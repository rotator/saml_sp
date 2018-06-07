SAML Service Provider
=====================

This package provides two modules:
- SAML Service Provider API
- SAML Drupal Login


The API module lets other modules leverage SAML authentication.

The SAML Drupal Login module specifically enables Drupal to become a "Service
Provider" for an IDP, so users can authenticate to Drupal (without entering a
username or password) by delegating authenticate to a SAML IDP (Identity
Provider).


Dependencies
============
Requires the OneLogin SAML-PHP toolkit which is managed by composer.

You can either manually require the module with composer:
    composer config repositories.drupal composer https://packages.drupal.org/8
    composer require drupal/saml_sp
to have composer download the module and the library. Or you can download the
module manually modify the core composer.json, change this section
    {
      "extra": {
        "_readme": [
          "By default Drupal loads the autoloader from ./vendor/autoload.php.",
          "To change the autoloader you can edit ./autoload.php."
        ],
        "merge-plugin": {
            "include": [
            "core/composer.json",
            "modules/saml_sp/composer.json" // <-- add this line
          ],
          "recurse": false,
          "replace": false,
          "merge-extra": false
        }
      },
    }
to add the modules/saml_sp/composer.json and run
    composer update
this will cause the library to be downloaded and added to your composer autoload.php


TODO
====
For the 8.x-2.x version there are a number of items that are still incomplete
- Single Log Out (SLO)
- updating Drupal account with attributes from the IdP

SimpleSamlPHP Configuration
===========================

First, configure your IdP in Drupal:
Note: Multiple IdPs can be configured, but only one is chosen to be used for the
Drupal login. This is good for development purposes, because different
environments (local, development, staging, production etc.) can be configured
with different App names and exported to code with Features. Then each
environment chooses a different IdP configuration for the Drupal login.

Name = Human readable name for IdP.

App Name: will be used in the IdP configuration. For example
"demoLocalDrupal".

NameID field: this defaults to user mail and works for most configurations. In
that case the IdP is configure to use email address for NameID.
But if you need to support changing email on the IdP, then you need to add
a custom field to user profile and then choose that field here. It is
recommended to use "Hidden Field Widgets" module (https://www.drupal.org/project/hidden_field)
for that field so that users don't need to worry about it, ever.

IDP Login URL: e.g. http:///myIdp.example.com/simplesaml/saml2/idp/SSOService.php
IDP Logout URL: e.g. http:///myIdp.example.com/simplesaml/saml2/idp/SingleLogoutService.php

x.509 certificate: Should correspond to the "certificate" field in
saml20-idp-hostd.php

Here's a sample config for saml20-sp-remote.php (when email is used for NameID):

$metadata['demoLocalDrupal'] = array(
'AssertionConsumerService' => 'http://mydrupal.example.com/drupal7/?q=saml/consume',
'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:email',
'simplesaml.nameidattribute' => 'uid',
'simplesaml.attributes' => FALSE,
);

Usage
=====

When everything is set and ready to go, the process begins from
http://www.yoursite.com/saml/drupal_login

A returnTo parameter can be appended to the url, if you want to redirect
the user somewhere else than the front page after login. For example the user
profile page http://www.yoursite.com/saml/drupal_login?returnTo=user

The login block and user login form will show a link with
"Log in using Single Sign-On" text on it. The user login page will return the
user to the profile page and the login block will return the user to the same page
where the login process was started from.

