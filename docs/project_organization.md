# Project Organization

I made a lot of folders and sub-folders, so I will outline all of the important folders for project development here.

## The Source Directory (/src)

This holds the source code for all of the different components of the system. Each subdirectory is a different module of the project. I modularize them like this because they lie perfectly on group boundaries, so one group only needs to worry about one directory.

- **public/**: holds information for the *app* docker container and has the PHP code that is responsible for hosting the webserver.
- **abet_private/**: holds sensitive PHP and configuration files that we don't want to be directly via the browser.
- **reportgen/**: will hold the source code for the [Report Generation](./reportgen/README.md) part of the project.
- **test/**: holds the pytest files that we may run to test the application.

## The Docker Directory (/docker)

This holds the information needed for the docker containers to run. Each subdirectory represents a different, distinct docker container with their own Dockerfile.

```filestrucutre
root
└── 📁docker
    └── 📁app
    └── 📁mysql
    └── 📁reportgen
    └── 📁test
    ├── docker_compose.yml
    └── demo.env
```

- **app/**: the PHP/Apache server that hosts the website on port 8080
- **mysql/**: the database that the server relies on. Database data is stored in `mysql_data`, and may need to be cleared if database schema is changed.
- **reportgen/**: will host the python fastapi interfaces that the app will use to execute backend functions.
- **test/**: holds the dockerfile for the selinium image in case we want to change it (very unlikely, but you never know...)

### docker-compose.yml

This file is what orchestrates every docker container to work together

### .env

There should be a .env that docker-compose.yml relies on. `.env` will hold important environment variables for production that I can't track. A template of the required .env files are in `demo.env` that has variables that we MUST change for progression.

## Scripts Directory (/scripts)

This directory contains useful scripts that I have been using for development througout the project. Once I create a global testing or deployment script, it will be here.

## Docs directory (/docs)

It's this directory! I will include important project documentation here, and any automatically generated files will also be here.
