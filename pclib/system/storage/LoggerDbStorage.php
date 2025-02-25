<?php

namespace pclib\system\storage;
use pclib\system\BaseObject;

/**
 *  This class is responsible for reading/writing of the log.
 * Override if you need different storage.
 */
class LoggerDbStorage extends BaseObject
{

/** var Logger */
protected $logger;

/** var Db */
public $db;

public $LOGGER_TAB   = 'LOGGER',
 $LABELS_TAB   = 'LOGGER_LABELS',
 $MESSAGES_TAB = 'LOGGER_MESSAGES';

function __construct(\pclib\Logger $logger)
{
	parent::__construct();
	$this->logger = $logger;
}

/**
 * Store logitem to the log.
 * @param array $logItem
 * @return $id
 **/
function log(array $logItem)
{
	$this->service('db');

	if ($logItem['LOGGER']) $logItem['LOGGER'] = $this->getLabelId($logItem['LOGGER'],1);
	if ($logItem['CATEGORY']) $logItem['CATEGORY'] = $this->getLabelId($logItem['CATEGORY'],4);
	if ($logItem['ACTION']) $logItem['ACTION'] = $this->getLabelId($logItem['ACTION'],2);
	if ($logItem['UA']) $logItem['UA'] = $this->getLabelId($logItem['UA'],3);

	$msg = $logItem['MESSAGE'];
	unset ($logItem['MESSAGE']);
	$id = $this->db->insert($this->LOGGER_TAB, $logItem);
	if ($msg) {
		$msgitem['LOG_ID'] = $id;
		$msgitem['MESSAGE'] = $msg;
		$msgitem['DT'] = date('Y-m-d H:i:s');
		$this->db->insert($this->MESSAGES_TAB, $msgitem);
	}
	return $id;
}

/**
 * Delete records from the log.
 **/
function delete($keepDays, $allLogs = false)
{
	$this->service('db');

	$date = date('Y-m-d H:i:s', time() - ($keepDays * 24 * 3600));
	if (!$allLogs) $logger = $this->getLabelId($this->logger->name,1);

	$this->db->delete($this->LOGGER_TAB,
	"DT<'{0}'
	~ AND LOGGER='{1}'", $date, $logger);
	$this->db->delete($this->MESSAGES_TAB, "DT<'{0}'", $date);

	$this->db->query("OPTIMIZE TABLE $this->LOGGER_TAB");
	$this->db->query("OPTIMIZE TABLE $this->MESSAGES_TAB");
}


/**
 * Translate text to text_id (using table LOGGER_LABELS)
 * If text is not found in the table, it is added.
 */
function getLabelId($label, $category)
{
	$this->service('db');
	
	list($id) = $this->db->select($this->LABELS_TAB.':ID',
		"LABEL='{0}' AND CATEGORY='{1}'", $label, $category
	);

	if (!$id) {
		$label = array('LABEL'=>$label,'CATEGORY'=>$category,'DT'=>date('Y-m-d H:i:s'));
		$id = $this->db->insert($this->LABELS_TAB, $label);
	}
	return $id;
}

/**
 * Return last $rowcount records from the log.
 * @param int $rowCount Number of rows
 * @param array $filter Set filter on USERNAME,ACTIONNAME,LOGGERNAME. Ex: array('USERNAME'=>'joe')
 * @return array Array of last records
 */
function getLog($rowCount, array $filter = null)
{
	$this->service('db');

	$this->db->setLimit($rowCount);
	$events = $this->db->selectAll(
		"select L.*,LM.MESSAGE, U.USERNAME, U.FULLNAME, LL4.LABEL AS CATEGORY,
		LL1.LABEL AS LOGGERNAME,LL2.LABEL AS ACTIONNAME,LL3.LABEL AS UA from $this->LOGGER_TAB L
		left join AUTH_USERS U on U.ID=L.USER_ID
		left join $this->LABELS_TAB LL1 on LL1.ID=L.LOGGER
		left join $this->LABELS_TAB LL2 on LL2.ID=L.ACTION
		left join $this->LABELS_TAB LL3 on LL3.ID=L.UA
		left join $this->LABELS_TAB LL4 on LL4.ID=L.CATEGORY
		left join $this->MESSAGES_TAB LM on LM.LOG_ID=L.ID
		 where 1=1
		~ AND L.LOGGER = '{LOGGER}'
		~ AND L.CATEGORY='{CATEGORY}'
		~ AND U.USERNAME like '{USERNAME}%'
		~ AND LL2.LABEL like '{ACTIONNAME}%'
		order by L.ID desc", $filter
	);

	foreach($events as $i => $tmp) {
		$events[$i]['IP'] = long2ip($events[$i]['IP']);
	}

	return $events;
}

protected function getTableSize($tableName)
{
	$dbName = $this->db->dbName();
	$size = $this->db->field(
		"select round(((data_length + index_length) / 1024 / 1024), 2) 
		FROM information_schema.TABLES 
		WHERE table_schema = '{0}'
		AND table_name = '{1}'", $dbName, $tableName
	);

	return $size;
}

function getSize()
{
	return $this->getTableSize('LOGGER') + $this->getTableSize('LOGGER_MESSAGES');
}

}
