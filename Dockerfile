# Use official PHP Apache image
FROM php:8.2-apache

# Install system dependencies and FFmpeg
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Configure PHP for large uploads and execution time
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 110M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 3600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Create uploads directory and set permissions
RUN mkdir -p uploads && chmod 777 uploads

# Redirect FFmpeg path in transcribe.php to the Linux path
# This will replace the Windows path we set earlier automatically during build
RUN sed -i "s|\$ffmpegPath = '.*';|\$ffmpegPath = 'ffmpeg';|g" api/transcribe.php

# Expose port 80
EXPOSE 80
