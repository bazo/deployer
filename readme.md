Deployer
=========

## What is it

Deployer is a paas style deployment application(like pagodabox or appfog). It is intended for deployment of web applications on private vps servers

## Features

Automatic deploy on push
Deploy any commit with one click
Releases history
Release re-deploy

### Todo
- rollback to previous release
- private keys controlled deploy access

## Prerequisites

- mongodb
- redis
- php 5.4
- git

Make sure your systems meets Nette Framework requirements: http://doc.nette.org/en/requirements

## Installation

- copy the application to the desired location
- run composer install
- run cli app:install - this will create the database and collections, default name is deployer
- run user:create to create your first app user

if you wish to deploy from the application gui, you will need to run a WAMP server and deploy worker script

- php wamp/server.php
- php workers/deployWorker.php

I suggest using supervisord to run these two processes in background. You can run as many deploy workers as you want, if you need to deploy multiple applications at once.
Only deploy of the same application can be run at one time. Automatic deploys do not use the deploy worker but are executed as post receive hook, in git process.

## Deploying applications

Add a new application by clicking the Add application button in navbar
Select a name and submit - Deployer will create a local bare git repository for you to push to
Go to application settings and set the root folder for your application
This folder will contain three subfolders:

- releases - holds all releases
- live - the folder to which you should point your webserver at, it's symlinked to the current release
- shared - contains folders that are shared between releases, commonly log and temp folders, uploads etc...

## Customizing the deploy process

You can customize the deploy process by adding a deploy.neon file to the root fodler of your application
The file has two sections:

shared_folders - list of folders you want to share between releases
hooks - commands executed at various stages of deployment:
- after_receive - executed just after the pushed code has been checked out, great for running composer install
- before_deploy - executed after the files were copied to the releases folder, before symlinking live folder
- after deploy - after symlinking the live folder to the release folder

commands can be anything that's executable on your server

sample deploy.neon file

```yaml
shared_folders:
	- temp
	- log
hooks:
	after_receive:
		- "composer install"
	before_deploy:
		- "php test.php"
	after_deploy:
		- "rm -rf temp/cache"
```