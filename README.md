# The Frontend-Backend interface for ABET-Tools

![Static Badge](https://img.shields.io/badge/ASU%20Capstone%20Project-8C1D40?logo=github&labelColor=red) ![GitHub commit activity](https://img.shields.io/github/commit-activity/w/hoang-danny05/ABET-Tools-Dockerized) ![GitHub contributors](https://img.shields.io/github/contributors-anon/hoang-danny05/ABET-Tools-Dockerized) ![GitHub issue custom search](https://img.shields.io/github/issues-search?query=repo%3Ahoang-danny05%2FABET-Tools-Dockerized%20is%3Aopen%20&label=open%20issues)

<!-- fancy icons from shields.io -->
<!--![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/hoang-danny05/ABET-Tools-Dockerized/WORKFLOW)-->

This is our application, which I've containerized for easier development. Included are containers for:

- The PHP/Apache server
- The MySQL database
- PHPMyAdmin for the database
- and the report generation API (as soon as the API is created)

I do not currently have a deployment command, but I aim to create a script to be able to deploy the server by next week.

## Getting Started

This requires:

- **Docker engine** AND **docker compose** to be installed. [Docker Install](#docker-installs)
- **CPanel** This also requires you to have the cPanel server private keys added. [ABET Private Key](#abet-private-key-setup)
- A bash command line. (a given)

Once that's done, you can run these commands to view the application

1) cd into `docker/` and create a `.env` file in that folder. `env.demo` is a format file that you can use. Please use this for development. [More info about .env files](#env-files)
2) within `docker/`, run `docker compose up --build`
3) you can visit [localhost port 8080](https://localhost:8080) to see the interface
4) you can visit [localhost port 8081](https://localhost:8081) to use phpMyAdmin

## ABET private key setup

Using the CPanel

1) download abet ssh key from the CPanel
2) Set correct permissions: `chmod 600 abet`
3) Move it to a convenient spot. usually `.ssh/`
4) `eval "$(ssh-agent -s)"`
5) `ssh-add $PATH_TO_ABET_PRIVATE_KEY`
6) Use the ABET ssh key password in the discord.

Note: you can automate step 4 and 5 if you create your own private key and put these commands in your `~/.bashrc`

## Docker Installs

You should follow official Docker installation. You got this. Installing Docker Desktop is the easiest way, regardless of OS.

### Linux

[Docker engine](https://docs.docker.com/engine/install)

[Docker compose](https://docs.docker.com/compose/install)

### Windows

[Docker desktop](https://docs.docker.com/desktop/setup/install/windows-install/) (Installs both!)

## Important Files

everything in `docker/` refers to the current containers that we have in the application. If we want more, we should add a folder with the container name, and a `docker-compose.yaml`.

**app container**: a php-apache container that runs both php and apache. Apache config is in `docker/apache2`, but you need to copy this from the server.

**mysql container**: the container containing the mysql instance and whose database is in `./docker/mysql/mysql_data`

if we want to integrate the python file, we can easily create a fastapi container in the docker-compose.

The rest of the project organization info is [HERE in the docs](docs/project-organization.md)

### `.env` files

this is the MOST important concept of configuration in the project. On first setup, you probably run `cp env.demo .env`, but you don't really know what that does. `docker/.env` stores all of the filled environment variables, and is used by `docker-compose.yml` which then may set the environment variables of the containers to those values.

`.env` stores ALL of our secret keys, but also our configuratoin information. Changing .env will change how the containers are built. I made `env.demo` with the purpose of easy setup, but these values should NEVER be exposed or used in the real server.

## Updating mysql tables

Updating mysql tables requires a reset of the `./docker/mysql/mysql_data` directory. To update the mysql tables, I usually run:

within the `docker/` folder

```bash
docker compose down     # or docker-compse if you have that
rm -rf ./mysql/mysql_data        # reset mysql_data so that tables can be updated.
docker compose up --build
```

## Pulling from the server

**NOTE:** `copy_from_server.bash` does not currently sync everything up, as we have not fully adopted this method. abet_private is copied into `src/abet_private/abet_private` and abet.asucapstonetools.com is copied into `src/public/abet.asucapstonetools.com`

1) git clone this repo.
2) cd into `scripts/` and run `copy_from_server.bash`
    - I hardcoded the server IP, so we might need to change this if the step doesn't work
    - The script also assumes you're in `scripts/`, else it will copy the files to who knows where.
3) git add, commit, and push your changes

## More information

More docs are located in the [docs directory](./docs/).
More information is found [in this master document.](https://docs.google.com/document/d/1mHOwIYyIZtg7FO8jtxTz9lPIuB3W9JVeVAR240YsTQA/edit?tab=t.88j2hx3zuwbr)
