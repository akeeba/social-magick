<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Model;

\defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * An MVC Model for the templates list view.
 *
 * @since   3.0.0
 */
class TemplatesModel extends ListModel
{
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = array_merge(
			$config['filter_fields'] ?? [],
			['search', 'id']
		);

		parent::__construct($config, $factory);
	}

	protected function populateState($ordering = 'word', $direction = 'asc')
	{
		$app = Factory::getApplication();

		// If we're under CLI there's nothing to populate
		if ($app->isClient('cli'))
		{
			return;
		}

		$search = $app->getUserStateFromRequest($this->context . 'filter.search', 'filter_search', '', 'string');
		$this->setState('filter.search', $search);

		parent::populateState($ordering, $direction);
	}

	protected function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');

		return parent::getStoreId($id);
	}

	protected function getListQuery()
	{
		/** @var DatabaseDriver $db */
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__admintools_badwords'));

		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (substr($search, 0, 3) === 'id:')
			{
				$id = (int) substr($search, 3);

				$query->where($db->quoteName('id') . ' = :id')
					->bind(':id', $id, ParameterType::INTEGER);
			}
			else
			{
				$search = '%' . $search . '%';

				$query->where($db->quoteName('title') . ' LIKE :search')
					->bind(':search', $search);
			}
		}

		// List ordering clause
		$orderCol  = $this->state->get('list.ordering', 'word');
		$orderDirn = $this->state->get('list.direction', 'asc');
		$ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);

		$query->order($ordering);

		return $query;
	}
}