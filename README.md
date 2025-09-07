# ![SocialMagick](https://github.com/akeeba/social-magick/blob/main/assets/banner/banner.png?raw=true)

Automatically generate OpenGraph images for Joomla! content.

> [!WARNING]  
> 🚧 **Work In Progress** 🚧 This plugin was recently transferred from Lucid Fox to Akeeba Ltd. We are reworking this plugin. Expect more news from this plugin in Q4 2025! 

## What is this

This plugin allows you to automatically generate OpenGraph images for your site's pages, superimposing text and graphics over an image or a solid colour background. OpenGraph images are used by social media sites when sharing a URL to any of your site's pages on them.

> [!TIP]
> If you want to preview your site's OpenGraph images you can use third-party sites such as [metatags.io](https://metatags.io/) and [opengraph.dev](https://opengraph.dev/).

## Requirements

This plugin has the following minimum requirements:

* Joomla 5.3 or later
* PHP 8.1, 8.2, 8.3, or 8.4
* The Imagick or GD PHP extension is installed and enabled. (If you're not sure how to do this, ask your host.)

## Quick start

* Download and install the plugin ZIP file.
* Publish the System – SocialMagick plugin.
* Edit the menu item you want to have OpenGraph images automatically generated. In its “OpenGraph images” tab:
* Set “Generate OpenGraph images” to Yes.
* Select the Solid template.
* Save your menu item.
* Go to [metatags.io](https://metatags.io/) and paste the URL to the page of your site that corresponds to the menu item you selected. You can now see that it has a preview image.

If you have menu items with core content (Joomla articles) categories and articles which make use of images, you can select the Overlay template. You will need to set the “Extra image source” option to “Intro image” or “Full Article image”, depending on which image you want to use.

The templates provided are meant as examples; while you are welcome to use them on your live site, you can also replace the template images with ones that do not have the SocialMagick watermark.

## History

This plugin was conceived in 2021 by Crystal Dionysopoulos of Lucid Fox. The code was written and had been maintained by us, with Crystal acting as the creative director. In 2025 Crystal decided to step back and transfer full ownership of the product back to us.

### TODO

This is meant as a quick brain-dump. Things here may or may not be implemented, and may end up becoming issues to handle later...

* [ ] Preview template
* [ ] Image effects: opacity, grayscale, sepia. Adjust them individually.
* [ ] Limit the form tabs for menu items, categories, and articles to specific user groups [gh-46]
* [ ] User group restriction for OG image preview. See notes on [gh-26].
* [ ] Rewrite documentation as DocBook XML [gh-14]
* [ ] Document that `imagick` or `gd` are required. Explain how you can use the PHP Information page in Joomla to determine if they are installed. Explain that if it's not enabled you can do that from the hosting control panel, or ask your host.
* [ ] Document that the lang strings are in the backend, but the overrides must be set for BOTH backend AND frontend
* [ ] Support SVGs. They can (usually) be rasterised using ImageMagick, see https://stackoverflow.com/questions/4809194/convert-svg-image-to-jpg-with-php  For GD see https://packagist.org/packages/meyfa/php-svg
* [ ] Extra image crop focus: face [gh-10]
* [ ] Auto-generating article intro and/or full text images [gh-6] (Check that the cache ID / sum matches the one used for the current intro image; we may have to store extra info in the article.)

We need plugins for titles, description, images.
    Images
        Item image source should come from plugin. For content default would be full text, intro text. For categories just category image.
        Templates would choose image source as none, item then custom, custom then item, custom.
    Title
        Plugins provide a default source
        Templates allow choosing title source as item then page, page then item
    Description
        Plugins provide a default source
        Templates allow choosing description source as item then page, page then item

#### Notes

Refactor cleaning old images:
* Move code from \Akeeba\Plugin\System\SocialMagick\Extension\Feature\Ajax into its own model
* Create an AJAX handler in the component's frontend
* Create a CLI plugin
* Create a Joomla Scheduled Tasks plugin