A CLI for interacting with the [Ravelry API](http://www.ravelry.com/api).

Consider this a functional prototype. This CLI's API may change. Not all the API calls have been tested.

*This project is not affiliated with [Ravelry](http://www.ravelry.com/).*


## Getting Started


### Download

It's easiest to download a pre-compiled [PHAR](http://php.net/phar) from the [releases](https://github.com/dpb587/ravelry-api-cli.php/releases) page...

    wget -qO ravelry-api https://github.com/dpb587/ravelry-api-cli.php/releases/download/v0.0.0/ravelry-api-cli.phar
    chmod +x ravelry-api
    ./ravelry-api current-user

For development, it's easiest to install with [Composer](https://getcomposer.org/)...

    git clone https://github.com/dpb587/ravelry-api-cli.php
    cd ravelry-api-cli.php
    composer.phar install
    ./bin/cli current-user


### Authentication

You'll need to configure your API keys through environment variables or CLI options...

 * access key - `RAVELRY_ACCESS_KEY` or `--auth-access-key`
 * secret key - `RAVELRY_SECRET_KEY` or `--auth-secret-key`
 * personal key - `RAVELRY_PERSONAL_KEY` or `--auth-personal-key`

You can find the your keys from the **apps** tab of your [Ravelry Pro](https://www.ravelry.com/pro) account.

If you're using OAuth instead of personal keys, there are two helper commands to make OAuth a bit easier...

 * `oauth:create` - use this to create an OAuth session
 * `oauth:confirm` - use this to finish authorizing the session if you get interrupted

By default, the OAuth tokens will be saved to `~/.ravelryapi` in JSON format.


### Usage

API methods are segmented by topic...

    $ ./ravelry-api
    ...snip...
      topics:reply                    Post a reply to a topic
      topics:show                     Get topic information
      topics:update                   Update a topic
    upload
      upload:image                    Upload an image file for later processing or attaching
      upload:request-token            Generate an upload token
      upload:status                   Get uploaded image IDs
    volumes
      volumes:show                    Get volume details
    yarns
      yarns:search                    Search yarn database
      yarns:show                      Get yarn details

API parameters are documented if you ask for `--help`...

    $ ./ravelry-api yarns:search --help
    Usage:
     yarns:search [--page="..."] [--page-size="..."] [--query="..."] [--sort="..."] [--facet="..."] [--debug] [--etag="..."] [--extras]

    Options:
     --query                Search term for fulltext searching yarns
     --page                 Result page to retrieve. Defaults to first page.
     --page-size            Defaults to 50 results per page.
     --sort                 Sort order. [allowed values: best, rating, projects]
     --facet                Facet key/value pairs derived from ravelry.com interface [KEY=VALUE] (multiple values allowed)
     ...snip...

API parameters are passed as options and the output, by default, is JSON...

    $ ./ravelry-api yarns:search --query 'cascade 220' --sort rating
    {
        "yarns": [
            {
                "rating_count": 924,
                "machine_washable": false,
                "texture": "Plied",
                "yarn_company_name": "Cascade Yarns",
                "max_gauge": 20,
                "yardage": 220,
                "rating_average": 4.49,
                "min_gauge": 18,
                "rating_total": 4153,
    ...snip...

If an API method supports arbitrary keys, you'll see a dedicated CLI option where you can use `KEY=VALUE` notation. For
example, several `*:search` methods allow you to use the facets that the main Ravelry website exposes...

    $ ./ravelry-api yarns:search --facet fiber=alpaca --facet weight=fingering ...snip...

If an API method accepts a file parameter, pass the file's path as the argument (or `-` to use `STDIN`)...

    $ ./ravelry-api upload:image --file0 ~/Desktop/my-stash.jpg ...snip...


## Debugging

You can increase the verbosity for more detailed logging...

 * `-v` - to dump the full API result to `STDOUT` (not just the simple JSON data from the API response body)
 * `-vv` - to dump the raw HTTP traffic from requests and responses to `STDERR`

Use `--debug-mock {file}` to mock the server response of an API call.

Use `--debug-log {file}` to append raw HTTP traffic and additional details to a separate file.


## License

[MIT License](./LICENSE)
