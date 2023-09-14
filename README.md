# MastoTrends RSS Converter

MastoTrends RSS Converter is a web application that converts the trending posts feed from a Mastodon instance into an RSS feed. This application does not require a database and is designed for simplicity and ease of use.

## Installation

To get started with MastoTrends RSS Converter, follow these steps:

1. Clone this repository to your web server:

```
git clone https://github.com/crisdias/mastotrends-rss.git
```

2. Install the required dependencies using [Composer](https://getcomposer.org/). If you don't have Composer installed, you can download it [here](https://getcomposer.org/download/).

```
composer install
```

3. Create a `.htaccess` file in the project root directory if you are using Apache and add the following rules to enable URL rewriting:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

4. Configure your web server to serve the project's `public` directory as the document root.

5. Access the application using your web server's URL. You can use the following URL pattern to access the MastoTrends RSS feed:

   http://your_domain/feed/instance

   Replace `your_domain` with your actual domain and `instance` with the name of the Mastodon instance you want to generate the feed for.

6. Enjoy your MastoTrends RSS feed!

## Usage

MastoTrends RSS Converter currently does not have a user-friendly interface for specifying the feed URL. To generate an RSS feed, simply use the following URL pattern:

http://your_domain/feed/instance

Replace `your_domain` with your actual domain and `instance` with the name of the Mastodon instance you want to generate the feed for.

## License

This project is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License. You are free to use and modify this software for non-commercial purposes as long as you provide proper attribution. Any derivative works must also be licensed under the same terms.

## To-Do

- Create a user-friendly interface for specifying the feed URL.
