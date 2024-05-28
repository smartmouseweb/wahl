<?php
namespace Service;

abstract class DB
{
  public static $pdo;

  public static function init() 
  {
	$dsn = "mysql:host=".getenv('DB_HOST').";dbname=".getenv('DB').";charset=".getenv('DB_CHARSET')."";

	$options = [
		\PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		\PDO::ATTR_EMULATE_PREPARES   => false,
	];

	self::$pdo = new \PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), $options);
  }

  public static function select(?array $params = [])
  {
	$results = [];

	$fields = isset($params['fields']) ? $params['fields'] : '*';
	$orderBy = isset($params['orderBy']) ? 'ORDER BY '.$params['orderBy'] : 'ORDER BY id ASC';
	$limit = isset($params['limit']) ? 'LIMIT '.$params['limit'] : '';
	$groupBy = isset($params['groupBy']) ? 'GROUP BY '.$params['groupBy'] : '';
	$having = isset($params['having']) ? 'HAVING '.$params['having'] : '';
	$conditionData = ['condition' => '', 'bindArray' => ''];

	if (isset($params['where']) || isset($params['additionalQuery']))
	{
		$conditionData = self::whereBuilder($params['where'], isset($params['additionalQuery']) ? $params['additionalQuery'] : '');
	}

	$sql = "SELECT ".$fields." FROM ".static::$dbTable." ".$conditionData['condition']." ".$groupBy." ".$having." ".$orderBy." ".$limit;
	// echo $sql.'<br>';
	$statement = self::$pdo->prepare($sql);
	
	if (is_array($conditionData['bindArray']))
	foreach ($conditionData['bindArray'] as $field => $value)
	{
		$statement->bindParam(':'.$field, $value, \PDO::PARAM_INT);
	}

	$statement->execute();

	if (isset($params['count'])) return $statement->rowCount();

	while ($row = $statement->fetch())
    {
      if (isset($params['index']))
	  {
		if ($params['index'] === true)
			$results[$row['id']] = $row;
		else
		if ($params['index'] !== false && $params['index'] !== '')
			$results[$row[$params['index']]] = $row;
	  }
      else
	  {
        $results[] = $row;
	  }
    }

	return $results;
  }

  private static function whereBuilder(?array $whereArray, ?string $additionalQuery = '')
  {
	$conditionArray = [];
	$bindArray = [];
	$condition = '';

  	if (isset($whereArray) && is_array($whereArray))
  	{
  		foreach ($whereArray as $field => $value)
  		{
			if (!in_array(substr(trim($field), -2), array(' >', ' <', '>=', '<=', '!=')) &&
				!in_array(substr(trim($field), -3), array(' IN')) &&
				!in_array(substr(trim($field), -4), array('LIKE')))
			{
				$conditions[] = "(".$field." = :".$field.")";
				$bindArray[$field] = $value;
			}
			else
			{
				if (in_array(substr(trim($field), -3), array(' IN')))
				{
					$conditions[] = "(".$field." (".$value."))";
				}
				else
				{
					$conditions[] = "(".$field."= :".$field.")";
					$bindArray[$field] = $value;
				}
			}
  		}
  	}

  	if (count($conditions) > 0)
  		$condition = 'WHERE ' . @implode(' AND ', $conditions). ' ' .($additionalQuery !== '' && !in_array(substr($additionalQuery,0,3), array('AND', ' AN', 'OR ', ' OR')) ? 'AND '.$additionalQuery : $additionalQuery);
  	else
  	if ($additionalQuery !== '')
  		$condition = 'WHERE ' .$additionalQuery;

  	return ['condition' => $condition, 'bindArray' => $bindArray];
  }

  public static function deleteById(int $id): bool
  {
	$statement = self::$pdo->prepare('DELETE FROM '.static::$dbTable.' WHERE id = :id LIMIT 1');
	$statement->bindParam(':id', $id, \PDO::PARAM_INT);
	return $statement->execute();
  }

  public static function delete(?array $params = []): bool
  {
	if (isset($params['where']) || isset($params['additionalQuery']))
	{
		$conditionData = self::whereBuilder($params['where'], isset($params['additionalQuery']) ? $params['additionalQuery'] : '');
	}

	$sql = "DELETE FROM  ".static::$dbTable." ".$conditionData['condition'];
	// echo $sql.'<br>';
	$statement = self::$pdo->prepare($sql);
	
	if (is_array($conditionData['bindArray']))
	foreach ($conditionData['bindArray'] as $field => $value)
	{
		$statement->bindParam(':'.$field, $value, \PDO::PARAM_INT);
	}

	return $statement->execute();
  }

}

?>
