## Developing locally

### Quickest: WordPress Playground

`script/playground` runs your checkout in [WordPress Playground](https://wordpress.org/playground/) (WordPress and PHP in WebAssembly, so there's no Docker or database to set up), logged in, with a sample blog to export. It's the same sample content as the Live Preview on WordPress.org, from `.wordpress-org/blueprints/blueprint.json`. You need Node.js 20 or later.

### Option 1: Using Dev Containers (Recommended)

The easiest way to get started is using [VS Code Dev Containers](https://code.visualstudio.com/docs/devcontainers/containers) or [GitHub Codespaces](https://github.com/features/codespaces):

1. Install [VS Code](https://code.visualstudio.com/) and the [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)
2. `git clone https://github.com/benbalter/wordpress-to-jekyll-exporter`
3. Open the folder in VS Code
4. Click "Reopen in Container" when prompted
5. Wait for the container to build and dependencies to install
6. Access WordPress at `http://localhost:8088`

The devcontainer includes:
- Pre-configured WordPress and MySQL
- All PHP extensions and Composer dependencies
- VS Code extensions for PHP development, debugging, and testing
- WordPress coding standards configured

See [.devcontainer/README.md](../.devcontainer/README.md) for more details.

### Option 2: Manual Setup

### Prerequisites

1. `sudo apt-get update`
1. `sudo apt-get install composer`
1. `sudo apt-get install php7.3-xml`
1. `sudo apt-get install php7.3-mysql`
1. `sudo apt-get install php7.3-zip`
1. `sudo apt-get install php-mbstring`
1. `sudo apt-get install subversion`
1. `sudo apt-get install mysql-server`
1. `sudo apt-get install php-pear`
1. `sudo pear install PHP_CodeSniffer`

### Bootstrap & Setup

1. `git clone https://github.com/benbalter/wordpress-to-jekyll-exporter`
2. `cd wordpress-to-jekyll-exporter`
3. `script/bootstrap`
4. `script/setup`

### Option 3: Docker Compose Only

1. `git clone https://github.com/benbalter/wordpress-to-jekyll-exporter`
2. `docker-compose up`
3. `open localhost:8088`

## Running tests

`script/cibuild`