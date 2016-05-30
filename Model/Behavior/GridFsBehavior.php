<?php
/**
 * GridFs 
 */
class GridFsBehavior extends ModelBehavior {
	public $name = 'GridFs';
	
	protected $_settings = [
		'size'	=> 1600000,
		'fields' => []
	];
	
	/**
	 * Сюда сохраняем GridFs текущей БД
	 * @var MongoGridFS
	 */
	protected $_Grid = null;
	
	public function setup(Model $Model, $config = []) {
		$this->_settings = $config;
		
		$this->_Grid = $Model->getDataSource()->getMongoDb()->getGridFS();
		
		return parent::setup($Model, $this->_settings);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ModelBehavior::beforeSave()
	 */
	public function beforeSave(Model $Model) {
		// проверяем необходимость сворачивания полей в gridfs
		foreach ($this->_settings['fields'] as $field) {
			$data = $Model->data[$Model->alias];
			$paths = explode('.', $field);
			
			foreach ($paths as $path) {
				if (!empty($data[$path])) {
					$data = $data[$path];
				} else {
					$data = null;
				}
			}
			$bytes = serialize($data);
			if (!empty($data) && mb_strlen($bytes, '8bit') > $this->_settings['size']) {
				// сохраняем содержимое поля в gridfs, а в значение поля записываем id в grid-е
				$value = $this->_Grid->storeBytes($bytes);
				
				if (count($paths) == 1) {
					$Model->data[$Model->alias][$paths[0]] = $value;
				} elseif (count($paths) == 2) {
					$Model->data[$Model->alias][$paths[0]][$paths[1]] = $value;
				} elseif (count($paths) == 3) {
					$Model->data[$Model->alias][$paths[0]][$paths[1]][$paths[2]] = $value;
				} elseif (count($paths) == 4) {
					$Model->data[$Model->alias][$paths[0]][$paths[1]][$paths[2]][$paths[3]] = $value;
				}
			}
		}
		
		return parent::beforeSave($Model);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ModelBehavior::afterFind()
	 */
	public function afterFind(Model $Model, $results, $primary) {
		if (!empty($results)) {
			if (array_key_exists($Model->alias, $results)) {
				$results[$Model->alias] = $this->_unserialize($results[$Model->alias]);
			} elseif (array_key_exists($Model->alias, $results[0]) && !array_key_exists('count', $results[0][$Model->alias])) {
				foreach ($results as &$result) {
					$result[$Model->alias] = $this->_unserialize($result[$Model->alias]);
				}
			}
		}
		
		return $results;
	}
	
	/**
	 * Разворачиваем данные назад из Grid-а в исходный массив
	 * 
	 * @param array $record данные содержащие то, что нужно развернуть
	 * @return готовый массив с развёрнутыми данными
	 */
	protected function _unserialize($record) {
		foreach ($this->_settings['fields'] as $field) {
			$paths = explode('.', $field);
			$data = $record;
			
			foreach ($paths as $path) {
				if (!empty($data[$path])) {
					$data = $data[$path];
				} else {
					$data = null;
				}
			}
			if (!empty($data) && ($data instanceof MongoId)) {
				// сохраняем содержимое поля в gridfs, а в значение поля записываем id в grid-е
				$value = @unserialize($this->_Grid->get($data)->getBytes());
				
				if (!empty($value)) {
					if (count($paths) == 1) {
						$record[$paths[0]] = $value;
					} elseif (count($paths) == 2) {
						$record[$paths[0]][$paths[1]] = $value;
					} elseif (count($paths) == 3) {
						$record[$paths[0]][$paths[1]][$paths[2]] = $value;
					} elseif (count($paths) == 4) {
						$record[$paths[0]][$paths[1]][$paths[2]][$paths[3]] = $value;
					}
				}
			}
		}
		
		return $record;
	}
}