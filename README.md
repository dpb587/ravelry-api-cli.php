A simple CLI to the Ravelry API.

Consider this a functional prototype. This CLI's API may change. Not all the API calls have been tested.

*This project is not affiliated with [Ravelry](http://www.ravelry.com/).*


## Getting Started

It's easiest to download a pre-compiled [PHAR](http://php.net/phar) from the [releases](https://github.com/dpb587/ravelry-api-php-cli/releases) page...

    wget -qO ravelry-api https://github.com/dpb587/ravelry-api-php-cli/releases/download/v0.0.0/ravelry-api.phar
    chmod +x ravelry-api
    ./ravelry-api current-user

For development, it's easiest to install with [Composer](https://getcomposer.org/)...

    git clone https://github.com/dpb587/ravelry-api-php-cli
    cd ravelry-api-php-cli
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


## Examples

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
     yarns:search [--query="..."] [--page="..."] [--page-size="..."] [--sort="..."] [--extras] [--etag="..."] [--debug]

    Options:
     --query                Search term for fulltext searching yarns
     --page                 Result page to retrieve. Defaults to first page.
     --page-size            Defaults to 50 results per page.
     --sort                 Sort order. [allowed values: best, rating, projects]
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

And now you should do cool things that somehow make your life easier. Like find the top-rated "cascade" yarns...

    $ ./ravelry-api yarns:search --query 'cascade' --sort rating --page-size 5 \
        | jq -r '( .paginator.results | tostring | "Top 5 of " + . + " results" ) , ( .yarns[] | " * " + .name + " (" + ( .rating_average | tostring ) + " stars) --> http://www.ravelry.com/yarns/library/" + .permalink )'
    Top 5 of 273 results
     * Cascade Petite (5 stars) --> http://www.ravelry.com/yarns/library/henrys-attic-cascade-petite
     * Dolly (5 stars) --> http://www.ravelry.com/yarns/library/cascade-yarns-dolly
     * Nantucket (5 stars) --> http://www.ravelry.com/yarns/library/cascade-nantucket
     * Tudor (5 stars) --> http://www.ravelry.com/yarns/library/dive-tudor
     * Colibri (5 stars) --> http://www.ravelry.com/yarns/library/bollicine-colibri

Or add yourself some stash and upload a photo along with it...

    $ ./ravelry-api stash:create --username "$RAVELRY_USER" \
        --handspun false \
        --location 'Basement Closet' \
        --notes 'Love this color!' \
        --pack:color-family-id 2 \
        --pack:colorway Carousel \
        --pack:dye-lot B9 \
        --pack:length-units yards \
        --pack:purchased-date 2014-05-26 \
        --pack:shop-id 3163 \
        --pack:skein-length 384 \
        --pack:skeins 2 \
        --pack:total-length 768 \
        --pack:weight-units grams \
        --stash-status-id 1 \
        --yarn-id 51846
    {
        "stash": {
            ...snip...
            "id": 10382615,
            ...snip...
        }
    }

    $ ./ravelry-api upload:request-token
    {
        "upload_token": "1978216-f19e7fe5a99ac4fba940d8c36c11c565"
    }

    $ ./ravelry-api upload:image --upload-token 1978216-f19e7fe5a99ac4fba940d8c36c11c565 \
        --file0 ~/Desktop/c01c550d-ceaa-11d7-d871-6eff22837f68~v2-210x210.jpg
    {
        "uploads": {
            "file0": {
                "image_id": 29178529
            }
        }
    }

    $ ./ravelry-api stash:create-photo --username "$RAVELRY_USER" --id 10382615 --image-id 29178529
    {
        "status_token": "job:ac725746ebfdf29a03b3b62bb86e2da0:1401376888"
    }

    $ ./ravelry-api photos:status --status-token job:ac725746ebfdf29a03b3b62bb86e2da0:1401376888
    {
        "complete": true,
        "progress": 100,
        "failed": false,
        "photo": {
            ...snip...
        }
    }


## Debugging

You can increase the verbosity for more detailed logging...

 * `-v` - to dump the full API result to `STDOUT` (not just the simple JSON data from the body)
 * `-vv` - to dump the raw HTTP traffic from requests and responses to `STDERR`

Use `--debug-mock {file}` to mock the server response of an API call.

Use `--debug-log {file}` to append raw HTTP traffic and additional details to a separate file.


## License

[MIT License](./LICENSE)
