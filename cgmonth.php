<?php
/** Plugin Task CGMonth
* Version			: 1.0.0
* Package			: Joomla 4.x
* copyright 		: Copyright (C) 2022 ConseilGouz. All rights reserved.
* license    		: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*
*/

defined('_JEXEC') or die;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Component\Content\Site\Model\ArticlesModel;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class PlgTaskCGMonth extends CMSPlugin implements SubscriberInterface
{
		use TaskPluginTrait;


	/**
	 * @var boolean
	 * @since 4.1.0
	 */
	protected $autoloadLanguage = true;
	/**
	 * @var string[]
	 *
	 * @since 4.1.0
	 */
	protected const TASKS_MAP = [
		'cinema' => [
			'langConstPrefix' => 'PLG_TASK_CGMONTH',
			'form'            => 'cgmonth',
			'method'          => 'cgmonth',
		],
	];
	protected $myparams;

	/**
	 * @inheritDoc
	 *
	 * @return string[]
	 *
	 * @since 4.1.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	protected function cgmonth(ExecuteTaskEvent $event): int {
		$app = Factory::getApplication();
		$this->myparams = $event->getArgument('params');
		$categories = $this->myparams->categories;
		if (is_null($categories)) {
			$res = $this->getAllCategories();
			$categories = array();
			foreach ($res as $catid) {
				if ($catid->count > 0) {
					$categories[] = $catid->id;
				}
			}
		}
		$articles     = new ArticlesModel(array('ignore_request' => true));
		if ($articles) {
		    $params = new Registry();
		    $articles->setState('params', $params);
		    $articles->setState('list.limit',0);
		    $articles->setState('list.start', 0);
		    $articles->setState('filter.tag', 0);
		    $articles->setState('list.ordering', 'a.ordering');
		    $articles->setState('list.direction', 'ASC');
			$articles->setState('filter.published', 1);
			$catids = $categories;
			$articles->setState('filter.category_id', $catids);		
			$articles->setState('filter.featured', 'show');
			$articles->setState('filter.author_id',"");
			$articles->setState('filter.author_id.include', 1);
			$articles->setState('filter.access', false);
			$excluded_articles = '';
			$items = $articles->getItems();		
			foreach ($items as $item)
			{
				$this->update_article_cgmonth($item);
			}	
		}
		return TaskStatus::OK;		
	}
	static function getAllCategories() {
		$db = Factory::getDbo();
		$query = $db->getQuery(true);
		$query->select('distinct cat.id,count(cont.id) as count,cat.note')
			->from('#__categories as cat ')
			->join('left','#__content cont on cat.id = cont.catid')
			->where('extension like "com_content" AND cat.published = 1  and cat.access = 1 and cont.state = 1')
			->group('catid')
		;
		$db->setQuery($query);
		return $db->loadObjectList();
	}
	
    private function update_article_cgmonth($article)
	{
		$item = [];
		$item['id'] = $article->id;
		$this->update_field($article);
		return true;
	}
	/* Update CG Month field  */
	function update_field($article) { 
		$item = [];
		$item['id'] = $article->id;
		$fields = FieldsHelper::getFields('com_content.article',$item);
		$date = ""; 
		$datefield = $this->myparams->datefield;
		$monthfield = $this->myparams->monthfield;
        foreach ($fields as $field) {
            if ($field->id == $datefield) {
                $dates = $field->value;
            }
        }
		if ($dates == "") return true;
		// au moins une date, on remplit la zone cgmonth
		$date = "";
		$lesdates = json_decode($dates, true);
		foreach ($lesdates as $unedate) { // on prend la date la plus petite
		    if ($date == "") $date = $unedate['date_debut'];
		    $dateMin = date_create($date);
		    $dateBase = date_create($unedate['date_debut']);
		    $diff = date_diff($dateMin, $dateBase);
		    if ($diff->format("%R") == '-') $date = $unedate['date_debut']; // date plus petite
		    $dateMin = date_create($date);
		    $dateJour = date_create(date("Y-m-d"));
		    $diff=date_diff($dateJour,$dateMin);
		    if ($diff->format("%R") == '-') $date = "";// date passee
		}
		if ($date != "") {// on a une date
            $model = BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));
 	        $value = date("Y-m-01", strtotime($date));
            $model->setFieldValue($monthfield, $article->id, $value);
		}
		// mise à jour de la date de creation
		$db = Factory::getDBO();
		$query = $db->getQuery(true);
		$created = new Date(date("Y-m-d", strtotime($date))." 00:00:01");
		$created = $db->quote($created);
		$query->update($db->quoteName('#__content'))
			->set($db->quoteName('created') . ' = '.$created)
			->where($db->quoteName('id') . ' = ' . (int) $article->id);
		$db->setQuery($query);
		$db->execute();
		return true;
	}
}