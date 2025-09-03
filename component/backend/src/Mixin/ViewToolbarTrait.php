<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

\defined('_JEXEC') || die;

use Joomla\CMS\Toolbar\Toolbar;

/**
 * Trait for handling toolbar compatibility between different Joomla versions.
 *
 * @since 3.0.0
 */
trait ViewToolbarTrait
{
    /**
     * Get the toolbar in a way that's compatible with different Joomla versions.
     *
     * @return  Toolbar
     *
     * @since   3.0.0
     */
    protected function getToolbarCompat(): Toolbar
    {
        $document = $this->getDocument();

        // Joomla 5 and later
        if (method_exists($document, 'getToolbar'))
        {
            return $document->getToolbar();
        }

        // Joomla 4.x
        /** @noinspection PhpDeprecationInspection */
        return Toolbar::getInstance('toolbar');
    }
}
