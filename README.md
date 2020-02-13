# WP-CLI Reference Command

[![Build Status](https://travis-ci.com/mehrshaddarzi/wp-cli-reference-command.svg?branch=master)](https://travis-ci.com/mehrshaddarzi/wp-cli-reference-command) ![Packagist Version](https://img.shields.io/packagist/v/mehrshaddarzi/wp-cli-reference-command) ![GitHub](https://img.shields.io/github/license/mehrshaddarzi/wp-cli-reference-command)

WordPress Code Reference in WP-CLI.

Quick links: [Installation](#installation) | [Using](#using) | [Contributing](#contributing)

## Installation

You can install this package with:

```console
wp package install mehrshaddarzi/wp-cli-reference-command
```

Installing this package requires WP-CLI v2 or greater. Update to the latest stable release with `wp cli update`.

## Using

```
NAME

  wp reference

DESCRIPTION

  WordPress Code Reference.

SYNOPSIS

  wp reference <class|method|function|hook>

```

### Search and show document

```console
wp reference absint
```
result :

![](https://raw.githubusercontent.com/mehrshaddarzi/wp-cli-reference-command/master/screenshot-1.jpg)


If your search results from more than one item.
for example:

````console
wp reference wp_insert_post
````

You will see a list to choose from.

![](https://raw.githubusercontent.com/mehrshaddarzi/wp-cli-reference-command/master/screenshot-2.jpg)

### Custom Search

By default WP_CLI reference package search between all WordPress class and functions.

If you want the custom search:

```console
wp reference --class=wp_user
```

```console
wp reference --funcion=wp_insert_post
```

```console
wp reference --method=get_row
```

```console
wp reference --hook=admin_footer
```


### Show in Web Browser

You can show WordPress code reference in Web browser after search with:

```console
wp reference --browser
```

### Cache system

By default, WP-CLI cached `1000` last searches for speed result. if you want to remove reference cache:

```console
wp cli cache clear
```

If you want only remove reference cache:

```console
wp reference --clear
```

## Author

- [Mehrshad Darzi](https://www.linkedin.com/in/mehrshaddarzi/) | PHP Full Stack and WordPress Expert

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.
Before you create a new issue, you should [search existing issues](https://github.com/mehrshaddarzi/wp-cli-reference-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/mehrshaddarzi/wp-cli-reference-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, please follow our guidelines for creating a pull request to make sure it's a pleasant experience:

1. Create a feature branch for each contribution.
2. Submit your pull request early for feedback.
3. Follow [PSR-2 Coding Standards](http://www.php-fig.org/psr/psr-2/).
