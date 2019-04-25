# WP-CLI Reference Command

WordPress Code Reference in WP-CLI.

Quick links: [Installation](#installation) | [Using](#using) | [Contributing](#contributing)

## Installation

you can install this package with `wp package install mehrshaddarzi/wp-cli-reference-command`.

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

```
wp reference absint
```
result :
```
# Structure

  absint( mixed $maybeint );

Summary   : Convert a value to non-negative integer.
Reference : Function
Url       : https://developer.wordpress.org/reference/functions/absint/
Source    : wp-includes/functions.php:4283

# Parameters

 $maybeint
  (mixed) {Required}
    Data you wish to have converted to a non-negative integer.


# Return

 (int) A non-negative integer.

# Changelog

+---------+-------------+
| Version | Description |
+---------+-------------+
| 2.5.0   | Introduced. |
+---------+-------------+
```

if your search results more than one item. 
for example :

````
wp reference wp_insert_post
````

You will see a list to choose from.

````
1. wp_insert_user() [Function]
     Source: wp-includes/user.php:1519
      Insert a user into the database.

2. wp_create_user() [Function]
     Source: wp-includes/user.php:2113
      A simpler way of inserting a user into the database.
      
Please enter the number list : 
````

### Custom Search

by default WP_CLI reference package search between all WordPress class and functions.

if you want custom search :

````
wp reference --class=wp_user
````

or

````
wp reference --funcion=wp_insert_post
````

or

````
wp reference --method=get_row
````

or

````
wp reference --hook=admin_footer
````


### Show in Web Browser

you can show WordPress reference code in Web browser after search with :

````
wp reference --browser
````

### Cache system

by default WP-CLI cached 100 last searches for speed result. if you want remove reference cache :

````
wp cli cache clear
````

if you want only removed reference cache :

````
wp reference --clear
````

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.