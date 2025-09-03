<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

defined('_JEXEC') || die;

use Joomla\CMS\Object\CMSObject;

/**
 * Trait for normalizing CMS objects.
 *
 * This is necessary since the legacy CMSObject class is deprecated, but not yet made obsolete. The Joomla core may be
 * using a mix of CMSObject and pure objects / class instances for a while. This abstracts all that noise away. Feel
 * free to steal this code ;)
 *
 * @since  3.0.0
 */
trait LegacyObjectTrait
{
    /**
     * Normalizes a possible CMS object to a standard object
     *
     * @param   mixed  $item  The item to normalize
     *
     * @return  mixed  The normalized item
     */
    private function normalizePossibleCMSObject($item)
    {
        if (!is_object($item))
        {
            return $item;
        }

        /** @noinspection PhpDeprecationInspection */
        if (class_exists(CMSObject::class) && !$item instanceof CMSObject)
        {
            return $item;
        }

        /** @noinspection PhpDeprecationInspection */
        return (object) $item->getProperties();
    }
}