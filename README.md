# ![Social Magick](https://github.com/akeeba/social-magick/blob/main/assets/banner/banner.png?raw=true)

Automatically generate OpenGraph images for Joomla! content.

> [!WARNING]  
> 🚧 **Work In Progress** 🚧 This plugin was recently transferred from Lucid Fox to Akeeba Ltd. We are reworking this plugin. Expect more news from this plugin in Q4 2025! 

## What is this

This plugin allows you to automatically generate Open Graph images for your site's pages, superimposing text and graphics over an image or a solid colour background. Open Graph images are used by social media sites when sharing a URL to any of your site's pages on them.

> [!TIP]
> If you want to preview your site's OpenGraph images you can use third-party sites such as [metatags.io](https://metatags.io/) and [opengraph.dev](https://opengraph.dev/).

## Requirements

This plugin has the following minimum requirements:

* Joomla 5.3 or later
* PHP 8.1, 8.2, 8.3, or 8.4
* The Imagick or GD PHP extension is installed and enabled. (If you're not sure how to do this, ask your host.)

## Quick start

* Download and install the plugin ZIP file.
* Publish the System – Social Magick plugin.
* Edit the menu item you want to have Open Graph images automatically generated. In its “Open Graph images” tab:
* Set “Generate Open Graph images” to Yes.
* Select the Solid template.
* Save your menu item.
* Go to [metatags.io](https://metatags.io/) and paste the URL to the page of your site that corresponds to the menu item you selected. You can now see that it has a preview image.

If you have menu items with core content (Joomla articles) categories and articles which make use of images, you can select the Overlay template. You will need to set the “Extra image source” option to “Intro image” or “Full Article image”, depending on which image you want to use.

The templates provided are meant as examples; while you are welcome to use them on your live site, you can also replace the template images with ones that do not have the Social Magick watermark.

## History

This plugin was conceived in 2021 by Crystal Dionysopoulos of Lucid Fox. The code was written and had been maintained by us, with Crystal acting as the creative director. In 2025 Crystal decided to step back, and transferred full ownership of the product back to us.