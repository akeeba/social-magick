<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Table;

use Akeeba\Component\SocialMagick\Administrator\Mixin\TableAssertionTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\TableColumnAliasTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\TableCreateModifyTrait;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') || die;

/**
 * Social Magic templates
 *
 * @property int      $id               The template's ID
 * @property string   $title            The template's title
 * @property string   $created          When was it created?
 * @property int      $created_by       Who was it created by?
 * @property string   $modified         When was it modified?
 * @property int      $modified_by      Who modified it?
 * @property int      $checked_out      Who checked it out?
 * @property string   $checked_out_time When was it checked out?
 * @property int      $ordering         Ordering when selecting a template.
 * @property Registry $params           Template parameters.
 *
 * @since 3.0.0
 */
class TemplateTable extends AbstractTable
{
	use TableCreateModifyTrait;
	use TableAssertionTrait;
	use TableColumnAliasTrait;

	public function __construct(DatabaseDriver $db, ?DispatcherInterface $dispatcher = null)
	{
		parent::__construct('#__socialmagick_templates', 'id', $db, $dispatcher);

		$this->created_by = Factory::getApplication()->getIdentity()?->id;
		$this->created    = Factory::getDate()->toSql();
	}

	/**
	 * Called after the reset operation is performed.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	protected function onAfterReset(): void
	{
		$this->params = new Registry();
	}

	/**
	 * Called before storing data, allowing for modifications or transformations.
	 *
	 * @param   bool  &$updateNulls  Indicates whether null values should be updated.
	 *
	 * @return  void
	 */
	protected function onBeforeStore(bool &$updateNulls): void
	{
		$this->params = $this->params->toString('JSON');
	}

	/**
	 * Called after the store operation is performed.
	 *
	 * @param   bool  &$result       Indicates the success or failure of the store operation.
	 * @param   bool  &$updateNulls  Determines whether null values should be updated.
	 *
	 * @return  void
	 */
	protected function onAfterStore(bool &$result, bool &$updateNulls): void
	{
		$this->params = new Registry($this->params ?: '{}');
	}

	/**
	 * Prepares the data before it is bound by ensuring parameters are properly structured.
	 *
	 * @param   mixed  $src     The source data to bind, passed by reference.
	 * @param   array  $ignore  An array of fields to ignore during binding, passed by reference.
	 *
	 * @return void
	 */
	protected function onBeforeBind(mixed &$src, array &$ignore = []): void
	{
		$src = (array) $src;

		$src['params'] = $src['params'] ?? '{}';

		if (!$src['params'] instanceof Registry)
		{
			$src['params'] = new Registry($src['params']);
		}
	}

	/**
	 * Performs operations before running the check process. Ensures that the required data is set.
	 *
	 * @return  void
	 */
	protected function onBeforeCheck(): void
	{
		$this->assertNotEmpty($this->title, 'COM_SOCIALMAGICK_TEMPLATE_ERR_TITLE_EMPTY');
	}
}