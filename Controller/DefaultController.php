<?php

namespace Glit\ResqueBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DefaultController extends Controller {

    /**
     * @Route("/")
     * @Template()
     */
    public function indexAction () {
        $queues = array();
        foreach ($this->get('glit_resque.queue_manager')->queues() as $queue) {
            $queues[$queue] = \Resque\Resque::size($queue);
        }

        $workers = array();
        foreach (\Resque\Worker::all() as $worker) {
            /** @var $worker \Resque\Worker */
            list($host, $pid, $_) = explode(':', $worker->__toString());
            $data = $worker->job();
            if(!empty($data)) {
                $workers[] = array(
                    'where' => $host . ':' . $pid,
                    'queue' => $data['queue'],
                    'job'   => $data['payload']['class']
                );
            }
        }

        return array(
            'queues'  => $queues,
            'workers' => $workers
        );
    }

    /**
     * @Route("/failed")
     * @Template()
     */
    public function failedAction() {
        $faileds = \Resque\Resque::redis()->lrange('failed', 0, -1);

        $data = array();
        foreach($faileds as $index => $fail) {
            $data[$index] = (json_decode($fail));

        }
        return array('data' => $data);
    }

    /**
     * @Route("/failed/{i}/requeue")
     */
    public function requeueFailedAction($i) {
        \Resque\Failure::requeue($i);

        return $this->redirect($this->generateUrl('glit_resque_default_failed'));
    }

    /**
     * @Route("/failed/{i}/delete")
     */
    public function deleteFailedAction($i) {
        $val = rand(0x000000, 0xffffff);
        \Resque\Resque::redis()->lset('failed', $i, $val);
        \Resque\Resque::redis()->lrem('failed', 1, $val);

        return $this->redirect($this->generateUrl('glit_resque_default_failed'));
    }

    /**
     * @Route("/stats/resque")
     * @Template()
     */
    public function statsResqueAction () {
        return array(
            'environment' => '',
            'failed'      => \Resque\Stat::get('failed'),
            'pending'     => 0,
            'processed'   => \Resque\Stat::get('processed'),
            'queues'      => count(\Resque\Resque::queues()),
            'servers'     => '',
            'workers'     => count(\Resque\Worker::all()),
            'working'     => 0
        );
    }

    /**
     * @Route("/stats/redis")
     * @Template()
     */
    public function statsRedisAction () {
        return array();
    }

    /**
     * @Route("/stats/keys")
     * @Template()
     */
    public function statsKeysAction () {
        $redis = \Resque\Resque::redis();

        $ids = $redis->keys('*');

        $keys = array();

        foreach ($ids as $key) {
            // Remove namespace from key
            $key = substr($key, strlen(\Resque\Redis::getNamespace()));

            // Find type
            $type = $redis->type($key);
            switch($type) {
                case 'set':
                    $data = $redis->smembers($key);
                    break;
                case 'list':
                    $data = $redis->lrange($key, 0, -1);
                    break;
                case 'string':
                    $data = $redis->get($key);
                    break;
                default:
                    $data = null;
                    break;
            }

            $keys[] = array(
                'id' => $key,
                'type' => $type,
                'data' => $data
            );
        }
        return array('keys' => $keys);
    }
}