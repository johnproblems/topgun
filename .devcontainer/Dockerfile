# Use Ubuntu as base image for maximum compatibility
FROM ubuntu:24.04

# Arguments
ARG USER_UID=1000
ARG USER_GID=$USER_UID
ARG USERNAME=vscode

# Avoid warnings by switching to noninteractive
ENV DEBIAN_FRONTEND=noninteractive

# Configure apt and install base packages
RUN apt-get update && apt-get install -y \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    wget \
    unzip \
    zip \
    sudo \
    vim \
    nano \
    openssl \
    openssh-client \
    inotify-tools \
    bash-completion \
    htop \
    iputils-ping \
    net-tools \
    dnsutils \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP 8.4 from Ondrej PPA
RUN add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y \
    php8.4-cli \
    php8.4-fpm \
    php8.4-common \
    php8.4-mysql \
    php8.4-pgsql \
    php8.4-sqlite3 \
    php8.4-redis \
    php8.4-curl \
    php8.4-gd \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-soap \
    php8.4-xdebug \
    php8.4-imagick \
    php8.4-opcache \
    php8.4-readline \
    php8.4-tokenizer \
    php8.4-fileinfo \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP
RUN echo "memory_limit = 512M" >> /etc/php/8.4/cli/php.ini \
    && echo "upload_max_filesize = 100M" >> /etc/php/8.4/cli/php.ini \
    && echo "post_max_size = 100M" >> /etc/php/8.4/cli/php.ini \
    && echo "max_execution_time = 300" >> /etc/php/8.4/cli/php.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

# Install Node.js 20 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm --version \
    && node --version

# Install global npm packages
RUN npm install -g npm@latest \
    && npm install -g yarn \
    && npm install -g pnpm

# Install PostgreSQL client
RUN apt-get update && apt-get install -y postgresql-client \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Redis client
RUN apt-get update && apt-get install -y redis-tools \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Docker CLI
RUN curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y docker-ce-cli docker-compose-plugin \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Terraform
RUN wget -O- https://apt.releases.hashicorp.com/gpg | gpg --dearmor -o /usr/share/keyrings/hashicorp-archive-keyring.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/hashicorp-archive-keyring.gpg] https://apt.releases.hashicorp.com $(lsb_release -cs) main" | tee /etc/apt/sources.list.d/hashicorp.list \
    && apt-get update \
    && apt-get install -y terraform \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && terraform --version

# Install GitHub CLI
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg \
    && chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | tee /etc/apt/sources.list.d/github-cli.list > /dev/null \
    && apt-get update \
    && apt-get install -y gh \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && gh --version

# Install GitHub Copilot CLI
RUN npm install -g @github/copilot

# Install Google Gemini CLI
RUN npm install -g @google/gemini-cli

# Install Claude Code (Anthropic's official CLI)
RUN npm install -g @anthropic-ai/claude-code

# Install useful development tools and yq
RUN apt-get update && apt-get install -y \
    jq \
    make \
    build-essential \
    python3 \
    python3-pip \
    && wget -q https://github.com/mikefarah/yq/releases/download/v4.40.5/yq_linux_amd64 -O /usr/bin/yq \
    && chmod +x /usr/bin/yq \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Create non-root user
RUN groupadd --gid $USER_GID $USERNAME \
    && useradd -s /bin/bash --uid $USER_UID --gid $USER_GID -m $USERNAME \
    && echo "$USERNAME ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/$USERNAME \
    && chmod 0440 /etc/sudoers.d/$USERNAME

# Add user to docker group
RUN groupadd -f docker \
    && usermod -aG docker $USERNAME

# Set up command history
RUN SNIPPET="export PROMPT_COMMAND='history -a' && export HISTFILE=/commandhistory/.bash_history" \
    && mkdir /commandhistory \
    && touch /commandhistory/.bash_history \
    && chown -R $USERNAME:$USERNAME /commandhistory \
    && echo "$SNIPPET" >> "/home/$USERNAME/.bashrc"

# Install Laravel installer globally
RUN composer global require laravel/installer \
    && echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> /home/$USERNAME/.bashrc

# Install commonly used Laravel/PHP tools
RUN composer global require friendsofphp/php-cs-fixer \
    && composer global require phpstan/phpstan \
    && composer global require rector/rector \
    && composer global require pestphp/pest

# Set up zsh with oh-my-zsh (optional, will be installed by devcontainer feature)
# This is handled by the devcontainer features

# Configure git
RUN git config --system core.autocrlf input \
    && git config --system pull.rebase false \
    && git config --system init.defaultBranch main

# Install additional helpful tools
RUN npm install -g tldr \
    && npm install -g http-server

# Create workspace directory
RUN mkdir -p /workspace \
    && chown -R $USERNAME:$USERNAME /workspace

# Switch to non-root user
USER $USERNAME

# Set working directory
WORKDIR /workspace

# Configure composer home directory
ENV COMPOSER_HOME=/home/$USERNAME/.composer
ENV PATH="/home/$USERNAME/.composer/vendor/bin:${PATH}"

# Switch back to dialog for any ad-hoc use of apt-get
ENV DEBIAN_FRONTEND=dialog

# Set default shell to bash
SHELL ["/bin/bash", "-c"]

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php -v && node -v && composer --version || exit 1

# Default command
CMD ["/bin/bash"]
