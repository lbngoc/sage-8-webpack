# Sage 8 with Webpack HMR

Fork of Sage 8 including webpack for assets compilation.


## Requirements

- [Node.js](http://nodejs.org/)
- [NPM](https://www.npmjs.com/) or [Yarn](https://yarnpkg.com/lang/en/)
- [Docker](https://www.docker.com/) and [Docker Compose](https://docs.docker.com/compose/install/)
- [WP-CLI](https://wp-cli.org)

## Installation

First, install `create-project` if you don't have it

```
npm install -g create-project
```

Create new project

```
create-project your-project-name lbngoc/sage-8-webpack#hmr
cd your-project-name
npm install
```

## Usage

**Setup Wordpress site**

Change your settings in `.env` file then run

```
npm run setup
```

**Development**

```
npm run serve
```

**Build**

```
npm run build
```

## References

- [Sage](https://github.com/roots/sage)
- [sage-8-webpack](https://github.com/drdogbot7/sage-8-webpack)
