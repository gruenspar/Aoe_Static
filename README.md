# AOE Static - Varnish caching for magento

This modules adds advanced varnish functionalitiy to your Magento shop.
The module handles the communication between Varnish and Magento, both
on filling and on purging the cache. Therefor the modules adds
information to the response header.

In addition to that, the module has the functionality to request customer
related information such as mini cart and login state via AJAX once the side 
is loaded. So this module also allows caching of pages when the customer
already has some items in the cart.

## Installation

The easiest way to install the module is with the use of [modman](/colinmollenhour/modman). 
Alternativly you can download the module as archive
and copy the folders accordingly. Don't simply copy the folder over your
magento installation, the pathes in the module are not the same as in the 
magento folder. See the modman file to get the relations.

## Usage

The module adds a new entry into you cache list. Enable the Varnish-Cache there.
Also there is a configuration section under Advanced -> System -> Varnish Configuration.

## Additional AJAX actions updating replaced blocks

Blocks configured to be dynamic are replaced in all actions' responses per default.

To have AJAX calls retrieve the original value of such dynamic block instead of its placeholder, add it to `{MyModule}/etc/config.xml`:

```
<?xml version="1.0" encoding="UTF-8"?>
<config>
    ...
    <global>
        <aoe_static>
            <ajax_action>
                <!-- Pattern: <module_controller_action />-->
                <ajaxcheckout_cart_delete />
            </ajax_action>
        </aoe_static>
     ...
```
This extension has its own phone call route to update dynamic blocks on all pages, which comes preconfigured as an aoestatic/ajax_action.
