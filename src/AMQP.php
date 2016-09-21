<?php

namespace Gimq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

class AMQP {

	/**
	 * 所有的连接
	 * @var array
	 */
	protected $_connections = [];

	/**
	 * 整理连接配置
	 * @var array
	 */
	protected $_configs = [];

	/**
	 * 默认连接名
	 * @var string
	 */
	private $__defaultConnection;

	/**
	 * 当前连接名
	 * @var string
	 */
	private $__currentConnection;

	/**
	 * 定义过的队列信息
	 * @var array
	 */
	private $__declaredQueue = [];


	function __construct($connection = FALSE) {
		if (!$connection) {
			$connection = config('amqp.default');
		}

		$this->__defaultConnection = $connection;
		$this->connection($connection);
	}

	/**
	 * 增加连接
	 * @author gjy
	 *
	 * @param  string $connection
	 * @return self
	 */
	public function connection($connection) {
		if (isset($this->_connections[$connection])) {
			goto tag_for_connection_return;
		}

		$config = config($key = 'amqp.connections.' . $connection);
		if (empty($config) || !is_array($config)) {
			throw new RuntimeException('Config error: missing ' . $key);
		}

		$this->_connections[$connection] = new AMQPStreamConnection(
			$config['host'],
			$config['port'],
			$config['user'],
			$config['password'],
			$config['vhost']
		);

		$this->_configs[$connection] = $config;
		$this->__currentConnection = $connection;

		tag_for_connection_return:
		return $this;
	}

	/**
	 * 重置默认连接
	 * 用于使用MQ::connection()->publish()之后再MQ::publish()时不会出现异常
	 * @author gjy
	 *
	 * @return void
	 */
	private function __resetConnection() {
		$this->__currentConnection = $this->__defaultConnection;
	}

	/**
	 * 获取配置中的channel_id
	 * @author gjy
	 *
	 * @param  string $queueAlias
	 * @return int | null
	 */
	private function _getChannelId($queueAlias) {
		if (empty($this->_configs[$this->__currentConnection]['queues'][$queueAlias]['channel_id'])) {
			throw new RuntimeException('Config Error: queue or channel_id does not exists.');
		}

		return $this->_configs[$this->__currentConnection]['queues'][$queueAlias]['channel_id'];
	}

	/**
	 * 使用当前连接获取channel
	 * @author gjy
	 *
	 * @param  string $queueAlias
	 * @return PhpAmqpLib\Channel\AMQPChannel
	 */
	private function _getChannel($queueAlias) {
		return $this->getInstance($this->__currentConnection)->channel($this->_getChannelId($queueAlias));
	}

	/**
	 * 定义队列, 定义交换机, 绑定路由等
	 * @author gjy
	 *
	 * @param  string $queue
	 * @param  boolean $withOutExchange
	 * @return array
	 */
	private function _queueDeclare($queueAlias, $withOutExchange = FALSE) {
		if (isset($this->__declaredQueue[$queueAlias])) {
			goto tag_for_queuedeclare_return;
		}

		if (empty($this->_configs[$this->__currentConnection]['queues'][$queueAlias])) {
			throw new RuntimeException('Config Error: queue does not exists.');
		}

		/** channel param */
		$queue = $this->_configs[$this->__currentConnection]['queues'][$queueAlias]['queue'];

		if ($withOutExchange) {
			// 不使用交换机时, exchange需要为空, routingKey需要为队列名称
			$exchangeType = '';
			$exchange = '';
			$routingKey = $queue;
		} else {
			$exchangeType = $this->_configs[$this->__currentConnection]['queues'][$queueAlias]['exchange_type'];
			$exchange = $this->_configs[$this->__currentConnection]['queues'][$queueAlias]['exchange_name'];

			if (isset($this->_configs[$this->__currentConnection]['queues'][$queueAlias]['queue_flags']['routing_key'])) {
				$routingKey = $this->_configs[$this->__currentConnection]['queues'][$queueAlias]['queue_flags']['routing_key'];
			} else {
				$routingKey = '';
			}
		}

		if (!$withOutExchange) {
			/**
			 * 定义交换机
			 *
			 * TODO 定义参数使用配置传入
			 * name: $exchange
			 * type: direct
			 * passive: false
			 * durable: true // the exchange will survive server restarts
			 * auto_delete: false //the exchange won't be deleted once the channel is closed.
			 */
			$this->_getChannel($queueAlias)->exchange_declare($exchange, $exchangeType, false, true, false);
		}

		/**
		 * 定义队列
		 *
		 * TODO 定义参数使用配置传入
		 * name: $queue
		 * passive: false
		 * durable: true // the queue will survive server restarts
		 * exclusive: false // the queue can be accessed in other channels
		 * auto_delete: false //the queue won't be deleted once the channel is closed.
		 */
		$this->_getChannel($queueAlias)->queue_declare($queue, false, true, false, false);

		if (!$withOutExchange) {
			// 绑定队列到交换机
			$this->_getChannel($queueAlias)->queue_bind($queue, $exchange, $routingKey);
		}

		// 保存定义过的队列信息用于发送
		$this->__declaredQueue[$queueAlias] = [
			'name' => $queue,
			'alias' => $queueAlias,
			'channelId' => $this->_getChannelId($queueAlias),
			'exchange' => $exchange,
			'routingKey' => $routingKey,
		];

		tag_for_queuedeclare_return:
		return $this->__declaredQueue[$queueAlias];
	}

	/**
	 * 发送单条消息
	 * @author gjy
	 *
	 * @param  string $message
	 * @param  string $queueAlias
	 * @param  boolean $withOutExchange
	 * @return boolean
	 */
	public function publish($message, $queueAlias, $withOutExchange = FALSE) {
		$queueInfo = $this->_queueDeclare($queueAlias, $withOutExchange);

		/** msg prop */
		if (isset($this->_configs[$this->__currentConnection]['queues'][$queueAlias]['message_properties'])) {
			$messageProp = $this->_configs[$this->__currentConnection]['queues'][$queueAlias]['message_properties'];
		} else {
			$messageProp = [];
		}

		$this->_getChannel($queueAlias)->basic_publish(
			new AMQPMessage($message, $messageProp),
			$queueInfo['exchange'],
			$queueInfo['routingKey']
		);

		$this->__resetConnection();
		return TRUE;
	}

	/**
	 * 批量发送消息
	 * @author gjy
	 *
	 * @param  array | Generator $message
	 * @param  string $queueAlias
	 * @param  boolean $withOutExchange
	 * @return boolean
	 */
	public function batchPublish($message, $queueAlias, $withOutExchange = FALSE) {
		if (is_array($message)) {
			$total = count($message);
			if ($total > 5000) {
				ini_set('memory_limit', $total > 10000 ? '1000M' : '500M');
			}
		} else if (is_object($message) && 'Generator' === get_class($message)) {
			ini_set('memory_limit', '500M');
		} else {
			throw new RuntimeException('Message type error: must be Array or Generator.');
		}

		$queueInfo = $this->_queueDeclare($queueAlias, $withOutExchange);

		/** msg prop */
		if (isset($this->_configs[$this->__currentConnection]['queues'][$queueAlias]['message_properties'])) {
			$messageProp = $this->_configs[$this->__currentConnection]['queues'][$queueAlias]['message_properties'];
		} else {
			$messageProp = [];
		}

		set_time_limit(0);

		foreach ($message as $v) {
			$this->_getChannel($queueAlias)->batch_basic_publish(new AMQPMessage($v, $messageProp), $queueInfo['exchange'], $queueInfo['routingKey']);
		}

		$this->_getChannel($queueAlias)->publish_batch();

		$this->__resetConnection();
		return TRUE;
	}

	// ================================================

	public function getInstance($connection = FALSE) {
		if (!$connection) {
			$connection = $this->__defaultConnection;
		}

		if (empty($this->_connections[$connection])) {
			$this->connection($connection);
		}

		return $this->_connections[$connection];
	}

	public function reconnect($connection = FALSE) {
		return $this->getInstance($connection)->reconnect();
	}

	public function close($connection = FALSE) {
		return $this->getInstance($connection)->close();
	}
}
