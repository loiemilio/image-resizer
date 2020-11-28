# Image Resizer

## What does it do?

This image resizer is JSON-based API service capable of receiving a list of images and resize (downscale or upscale) them to 100x100 pixels.

The resizing process is asynchronous and the API immediately returns the UUID of the assigned job. 

The UUID can be used to check the job status, retrieve the resized images or cancel the job.

A worker takes care of jobs queue and either sends the resized images to a webhook specified within the request or stores them for a certain amount of time.

In this second instance the application acts as an ephemeral file system deleting the resized images once they have been retrieved or have expired.

## Run it

1. [Install Docker Compose](https://docs.docker.com/compose/install/)

2. [Install Composer](https://getcomposer.org/download/)

3. Install php dependencies 
	
	`composer install`
	
4. Build and start the containers

   `docker-compose up` or `docker-compose up -d` for a detached lauch

5. Send your requests to 

   `http://127.0.0.1:8081` 

## Use it

There are only 3 endpoints:

### `POST /`

It's the way of sending the images to resize. 

The request payload must be an object with at least an array property named `images`. 

Every entry of this array represents an image and is comprised of two string properties:

- **`name`** It's the unique identified of the image for this request, it can't be reused across two or more images in the same request. It should not contains slashes `/`,`\` or colons `:`
- **`data`** It's the base64 encoded image.

If you wish to receive a webhook request when the job has been processed you can specify add an url field named `webhook` to the payload. 

So that a valid request looks something like:

```json
{
  "images": [
    {"name": "image.jpg", "data": "\/9j\/4AAQSk....MflFbbx\/\/9k="},
    {"name": "I_AM_A_VALID_NAME", "data": "\/9j\/4AAQ4....SIks1d\/\/9k="},
  ]
}
```

or this:

```json
{
	"webhook": "https://myhost/mypath"
  "images": [
    {"name": "image.jpg", "data": "\/9j\/4AAQSk....MflFbbx\/\/9k="},
    {"name": "I_AM_A_VALID_NAME", "data": "\/9j\/4AAQ4....SIks1d\/\/9k="},
  ]
}
```

The application simply returns the UUID of the job associated to the request:

```json
{
	"uuid":"ab0e700d-cbfb-467e-a38a-14291ca3b51f"
}
```



<hr>

### `GET /{uuid}`

It allows to monitor the status of a job. A request to this endpoint can also be used to retrieve the resized images if no webhook parameters has been specified in the originating request payload.

Possible responses are:

- *202 Images not yet processed.* when the job hasn't been yet completed.
- *404 UUID not found.* when the job UUID is unknown or it has failed or its images have expired.
- *200 Success.* when the job completed successfully and the image are ready

In case of *200 Success.* the response payload is specular to the originating request with the images `data` fields now being the base64 encoded version of the resized images and with the addition of the UUID of the job.

```json
{
	"uuid": "ab0e700d-cbfb-467e-a38a-14291ca3b51f"
  "images": [
    {"name": "image.jpg", "data": "\/9j\/4AAQSk....MflFbbx\/\/9k="},
    {"name": "I_AM_A_VALID_NAME", "data": "\/9j\/4AAQ4....SIks1d\/\/9k="},
  ]
}
```

<hr>

### `DELETE /{uuid}`

It allows to cancel a job. If the job hasn't been yet processed it also deletes the original images from the storage. Otherwise it deletes the resized images if not yet retrieved.

The only possible response is:

*204 No Content*

<hr>

## Test it

Once the app container has started it's possible to run 

## Configure it

The application makes use of the following services:

- **[nginx](https://www.nginx.com/)** as the web server running on port `8081`
- **[Redis](https://redis.io/)** to store some run-time variables and manage the job queues. It runs on port `6380`

Ports in use by these services can be configured in `docker-compose.yml` before starting the containers.

### .env

The application behaviour can be altered playing with the following environment variables:

**`RESIZER_DISK`**

 The application is based on Laravel, it uses the Laravel's concept of filesystems to store the received and resized images. You can configure your preferred disk (local, S3, GDrive) in `config/filesystems.php` `disks` array and provide its name in `RESIZER_DISK` envvar in order to make the Image Resizer use it.

**`RESIZER_THROTTLING_ALLOW`** 

**`RESIZER_THROTTLING_EVERY`** 

The requests for new resizes are throttled to `RESIZER_THROTTLING_ALLOW` requests every `RESIZER_THROTTLING_EVERY` minutes per sender IP. 

Both parameters are integer.

**`RESIZER_MAX_IMAGE_SIZE`**

It is the maxium file size in Kb for a single image.



### Laravel configuration

The application uses the Laravel queues. The `QUEUE_CONNECTION` is set to `Redis` by default.



