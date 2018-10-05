# Sage 8 with Webpack HMR

Fork of Sage 8 including webpack for assets compilation.

```
                            _  _
Maintained by              | || |
 _ __    __ _   ___    ___ | || |__       ___   ___   _ __ ___
| '_ \  / _` | / _ \  / __|| || '_ \     / __| / _ \ | '_ ` _ \
| | | || (_| || (_) || (__ | || |_) | _ | (__ | (_) || | | | | |
|_| |_| \__, | \___/  \___||_||_.__/ (_) \___| \___/ |_| |_| |_|
         __/ |
        |___/    Feel free to touch me at contact@ngoclb.com

```

## 1. Requirements

- [Node.js](http://nodejs.org/)
- [NPM](https://www.npmjs.com/) or [Yarn](https://yarnpkg.com/lang/en/)
- [Docker](https://www.docker.com/) and [Docker Compose](https://docs.docker.com/compose/install/)
- [WP-CLI](https://wp-cli.org)
- [Deployer](https://deployer.org)

## 2. Installation

First, install `create-project` if you don't have it

```
npm install -g create-project
```

Create new project

```
create-project your-project-name lbngoc/sage-8-webpack#dep
cd your-project-name
npm install
```

## 3. Demo

- [Setup a stand-alone project](https://youtu.be/X26GtB1r5NU)
- [Deploy a local website to host with Deployer](https://youtu.be/HNai59M4DsQ)

## 4. Usage

### 4.1 Setup dev enviroment

#### 4.1.1 For stand-alone project

Create new `docker-compose.override.yml`

```
version: "3"
services:
  mysql:
    restart: always
    image: ${DB}
    volumes:
      - "./.data/db:/var/lib/mysql"
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}

  wordpress:
    depends_on:
      - mysql
    ports:
      - "${WPSITE_PORT:-80}:80"

networks:
  default:
    driver: bridge
```

Skip below section and continue at **[4.2 Development](#32-development)**

#### 4.1.2 For multiple projects (use sample network and database server)

***Skip this step if you was already setup it***

**Approach**

For example on my computer, I have my working space structure at below:

```
Working
├── Project_A
│   ├── docs
│   ├── src
│   └── ...
├── Project_B
│   ├── docs
│   ├── designs
│   ├── sample
│   └── ...
├── ...
├── docker-compose.yaml
...
```

In this case, I have to develop 2 projects A & B at same time, but I don't want to run them in multiple container and define seperate ports for each project. I want to use virtual host to access them. eg. `http://project-a.local` and `http://project-b.local` and manage their database with on same server.

To do this idea, we should setup a shared network with proxy (traefik) and some common containers (mysql, mailhog, adminer,  etc..) first.

Create new `docker-compose.yml` at Working directory:

```
version: "3"

services:
  proxy:
    restart: always
    image: traefik
    networks:
      - reverse_proxy
    ports:
      - "80:80"
      - "443:443"
      - "8080:8080"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - ./traefik.toml:/traefik.toml

  mysql:
    restart: always
    image: mysql:5.7
    networks:
      - reverse_proxy
    ports:
      - "3306:3306"
    labels:
      - "traefik.enable=false"
    environment:
      MYSQL_ROOT_PASSWORD: root

  adminer:
    image: adminer
    restart: always
    networks:
      - reverse_proxy
    labels:
      - 'traefik.backend=adminer'
      - 'traefik.port=8080'
      - 'traefik.frontend.rule=Host:adminer.local'
      - 'traefik.frontend.entryPoints=http'

networks:
  reverse_proxy:
    driver: bridge

```

Then start it `docker-compose up -d`, that's all.

**Setup develop domain TLD**

You can see on above example, I use `.local` as development domain, so we should point them to localhost to keep them working fine.

Edit `/etc/hosts` and add below code:

```
127.0.0.1   adminer.local
127.0.0.1   project-a.local
127.0.0.1   project-b.local
```

You can setup all domain with TLD `.local` to locahost with [dnsmaq](http://www.thekelleys.org.uk/dnsmasq/doc.html)

### 4.2 Development

**Setup Wordpress site**

Change your settings in `.env` file then run

```
npm run setup
```

**Development**

```
npm run dev
```

**Build**

```
npm run build
```

### 4.3 Deployment

Edit your host details inside `hosts.yml`

For deploy a new release to your host

```
dep deploy
```

If you only need update wp-content folder

```
dep deploy:update_code

```

Some useful deploy tasks to synchronize between local and host

```
 pull
  pull:db             Download database from host and import to local
  pull:media          Download media files from host
  pull:plugins        Download plugins from host
  pull:theme          Download only activate theme from host
  pull:themes         Download themes from host
 push
  push:db             Upload and import local database to host
  push:media          Upload media files to host
  push:plugins        Upload plugins to host
  push:theme          Upload only activate theme to host
  push:themes         Upload themes to host
```

## 5. References

- [Sage](https://github.com/roots/sage)
- [sage-8-webpack](https://github.com/drdogbot7/sage-8-webpack)
