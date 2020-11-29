# Image Resizer

## What does it do?

The *Image Resizer* is JSON-based API service capable of receiving a list of images and resize (downscale or upscale) them to 100x100 pixels.

The resizing process is asynchronous and the API immediately returns the UUID of the assigned job. 

The UUID can be used to check the job status, retrieve the resized images or cancel the job.

A worker takes care of jobs queue and either sends the resized images via a POST request to a webhook specified within the request or stores them for a certain amount of time.

In this second instance the application acts as an ephemeral file system deleting the resized images once they have been retrieved or have expired.

## Run it

1. [Install Docker Compose](https://docs.docker.com/compose/install/)

2. [Install Composer](https://getcomposer.org/download/)

3. Install php dependencies 
	
	`composer install`
	
4. `cp .env.example .env`
	
5. Build and start the containers

   `docker-compose up` or `docker-compose up -d` for a detached lauch

6. Send your requests to 

   `http://127.0.0.1:8081` 

## Use it

There are only 3 endpoints:

### `POST /`

It's the way of sending the images to resize. 

The request payload must be an object with at least an array property named `images`. 

Every entry of this array represents an image and consists of two string properties:

- **`name`** It's the unique identified of the image for this request, it can't be reused across two or more images in the same request. It should not contain slash `/`,`\` or colon `:` characters.
- **`data`** It's the base64 encoded image.

If you wish to receive a webhook request when the job has been processed you can specify add an url field named `webhook` to the payload. 

So that a valid request looks something like:

```json
{
  "images": [
    {"name": "image.jpg", "data": "\/9j\/4AAQSk....MflFbbx\/\/9k="},
    {"name": "I_AM_A_VALID_NAME", "data": "\/9j\/4AAQ4....SIks1d\/\/9k="}
  ]
}
```

or this:

```json
{
	"webhook": "https://myhost/mypath",
  "images": [
    {"name": "image.jpg", "data": "\/9j\/4AAQSk....MflFbbx\/\/9k="},
    {"name": "I_AM_A_VALID_NAME", "data": "\/9j\/4AAQ4....SIks1d\/\/9k="}
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
	"uuid": "ab0e700d-cbfb-467e-a38a-14291ca3b51f",
  "images": [
    {"name": "image.jpg", "data": "\/9j\/4AAQSk....MflFbbx\/\/9k="},
    {"name": "I_AM_A_VALID_NAME", "data": "\/9j\/4AAQ4....SIks1d\/\/9k="}
  ]
}
```

<hr>

### `DELETE /{uuid}`

It allows to cancel a job. If the job hasn't been yet processed it also deletes the original images from the storage; otherwise it deletes the resized images if not yet retrieved.

The only possible response is:

*204 No Content*

<hr>

## Test it

Once the app container has started it's possible to run the test with the following command:

`docker-compose exec app ./vendor/bin/phpunit` 

The tests don't require any other service than the app to complete.

<hr>

## How it's made

The *Image Resizer* is a Laravel based project.

It uses [intervention/image](http://image.intervention.io/) to resize the images and [Redis](https://redis.io/) as a light database to handle the queue and some runtime variables.

There is only one controller that handles the requests to the three possible endpoints:

**`POST /`** is served by `Controller@upload`. The framework validates the request as for rules specified in the `UploadImageRequest` class. 

Images must be provided in an `images` array, each of them must have an unique `name` and a base64 encoded `data` content, smaller than `config('resizer.max-image-size')` Kb and whose mimetype is among `config('resizer.allowed-mimetypes')` list of allowed mimetypes.  

An optional webhook parameter must be a valid url.

The controller method just dispatches a `ResizeImage` job to the queue passing it a new UUID and the validated request.

### ResizeImage job

Its construct receives the saves the request parameters and stores the validated images in the storage under a folder named `{uuid}`. It also stores the job expiry time in Redis with key `image-exp-{uuid}`.

When retrieved from the queue the job creates a resized version of each uploaded image. 

If the request had the webhook parameter it then passes the list of resized image to the `AsyncClient` that will send them to the specified webhook. It the immediately delete the `{uuid}` folder and its content.

Otherwise it stores the resized images in place of the originally uploaded ones and sets a new Redis entry with key `image-done-{uuid}` to `true` to signal that the job has been completed.

### AsyncClient

The `AsyncClient` is a simplistic implementation of a fire and forget POST only http client. It utilises an Interest socket to send the request and immediately close the connection ignoring the response.

**`GET /{uuid}`** is served by `Controller@show`.

It quickly checks for the existence of a job with the provided UUID. If found it can return the list of resized images (and immediately delete their folder by dispatching a `DeleteImages` job), or return a `204 Image not yet processed.` message if the job has not been yet processed.

**`DELETE /{uuid}`** is served by `Controller@destroy`.

As the signature suggest, it simply dispatches a `DeleteImages` job to delete any images associated with that job UUID.

### DeleteImages job

It access an UUID and deletes the corresponding folder on the storage and associated Redis records if they exist.

The *Image Resizer* has also an Artisan command `flush:images`. This checks for jobs that have expired for each of them and dispatches a `DeleteImages` job.

The scheduler executes the `flush:images` command every minute.

### The scheduler and the queue worker

This are realised as two command services in the docker-compose configuration.

The first service executes the script located in `.docker/bin/run-scheduler.sh` that launches the Artisan schedule forever every minute.

The second service script (`.docker/bin/run-worker.sh`) executes instead the Arisan `queue:work` command with 3 possible retries, and a maximum execution time of 90'.

## Configure it

The application makes use of the following services:

- **[nginx](https://www.nginx.com/)** as the web server running on port `8081`
- **[Redis](https://redis.io/)** to store some run-time variables and manage the job queues. It runs on port `6380`

Ports in use by these services can be configured in `docker-compose.yml` before starting the containers.

The nginx host configuration folder is  `.docker/nginx/conf.d/` mounted as `/etc/nginx/conf.d/` in the container. 

The folder `.docker/data/redis` is mounted as data folder for the Redis container.

### .env

The application behaviour can be altered playing with the following environment variables:

**`RESIZER_DISK`**

 The application is based on Laravel, it uses the Laravel's concept of filesystems to store the received and resized images. You can configure your preferred disk (local, S3, GDrive) in `config/filesystems.php` `disks` array and provide its name in `RESIZER_DISK` envvar in order to make the Image Resizer use it.

**`RESIZER_THROTTLING_ALLOW`** 

**`RESIZER_THROTTLING_EVERY`** 

New resizes requests are limited to `RESIZER_THROTTLING_ALLOW` requests every `RESIZER_THROTTLING_EVERY` minutes per sender IP. 

Both parameters are integer.

**`RESIZER_MAX_IMAGE_SIZE`**

It is the maxium file size in Kb for a single image.

**`RESIZER_MAX_JOB_LIFETIME`**

It's php parseable date interval, i.e. `+1 hour` or `tomorrow`. It specifies after how long a job that hasn't been yet processed or has completed can be discarded and with it the associated images.

### Application configuration file

The dedicated configuration file is named `resizer.php`. While most of the parameters can be simply overridden by the previous environment variables, here it's possible to change them programmatically or do some computation before the final values. 

Moreover it contains the `allowed-mimetypes` parameter that is an array of accepted mimetypes for the images.

### Laravel configuration

The application uses the Laravel queues. The `QUEUE_CONNECTION` is set to `Redis` by default. It is possible to set it to `sync` to immediately process the incoming requests.

<hr>

### What's wrong

1. PHP for manipulating images isn't a great choice when it comes to high volumes.

2. The `intervention/image` configuration is not exposed and can't be customised without touching the application code.

3. It doesn't accept any image manipulation parameters such as preserve aspect ratio, compression level etc...

4. Jobs that fail are as no-jobs. The user can't get any details about them as they were never created.

5. Accepting base64 encoded images means no support is given through `Illuminate\Support\UploadedImage` and checks around filesize and mimetype must be implemented manually

6. No logging

7. Many other things: it's late, it has been a hell of a week, my daughter has finally stopped screaming and I just want to rest. I'm available from Monday to discuss everything else.

   



