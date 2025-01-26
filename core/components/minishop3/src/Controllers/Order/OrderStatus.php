<?php

namespace MiniShop3\Controllers\Order;

use MiniShop3\Controllers\Payment\Payment;
use MiniShop3\MiniShop3;
use MiniShop3\Model\msOrder;
use MiniShop3\Model\msOrderStatus;
use MODX\Revolution\modChunk;
use MODX\Revolution\modContextSetting;
use MODX\Revolution\modUserProfile;
use MODX\Revolution\modUserSetting;
use MODX\Revolution\modX;

class OrderStatus
{
    /** @var modX */
    public $modx;
    /** @var MiniShop3 */
    public $ms3;
    /**
     * @var OrderLog
     */
    private $orderLogController;
    private $useScheduler;
    private $schedulerTask;

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $ms3->modx;
        $this->orderLogController = new OrderLog($ms3);

        $this->modx->lexicon->load('minishop3:default');
    }

    /**
     * Switch order status
     *
     * @param integer $order_id The id of msOrder
     * @param integer $status_id The id of msOrderStatus
     *
     * @return boolean|string
     */
    public function change(int $order_id, int $status_id): bool|string
    {
        /** @var msOrder $order */
        $msOrder = $this->modx->getObject(msOrder::class, ['id' => $order_id]);
        if (!$msOrder) {
            return $this->modx->lexicon('ms3_err_order_nf');
        }
        $ctx = $msOrder->get('context');
        $this->modx->switchContext($ctx);
        $this->ms3->initialize($ctx);

        /** @var msOrderStatus $status */
        $status = $this->modx->getObject(msOrderStatus::class, ['id' => $status_id, 'active' => 1]);
        if (!$status) {
            return $this->modx->lexicon('ms3_err_status_nf');
        }
        /** @var msOrderStatus $old_status */
        $old_status = $this->modx->getObject(
            msOrderStatus::class,
            ['id' => $msOrder->get('status_id'), 'active' => 1]
        );
        if ($old_status) {
            if ($old_status->get('final')) {
                return $this->modx->lexicon('ms3_err_status_final');
            }
            if ($old_status->get('fixed')) {
                if ($status->get('position') <= $old_status->get('position')) {
                    return $this->modx->lexicon('ms3_err_status_fixed');
                }
            }
        }

        if ($msOrder->get('status_id') == $status_id) {
            return $this->modx->lexicon('ms3_err_status_same');
        }

        $eventParams = [
            'msOrder' => $msOrder,
            'old_status' => $old_status->get('id'),
            'status' => $status_id,
        ];
        $response = $this->ms3->utils->invokeEvent('msOnBeforeChangeOrderStatus', $eventParams);
        if (!$response['success']) {
            return $response['message'];
        }

        $msOrder->set('status_id', $status_id);

        if ($msOrder->save()) {
            $this->orderLogController->add($msOrder->get('id'), $status_id, 'status');
            $response = $this->ms3->utils->invokeEvent('msOnChangeOrderStatus', [
                'msOrder' => $msOrder,
                'old_status' => $old_status->get('id'),
                'status' => $status_id,
            ]);
            if (!$response['success']) {
                return $response['message'];
            }

            $this->useScheduler = $this->modx->getOption('ms3_use_scheduler', null, false);
            $this->schedulerTask = null;
            if ($this->useScheduler) {
                $this->setSchedulerTask();
            }

            $pls = $this->preparePls($msOrder);
            //TODO  добавить другие контроллеры связи SMS, telegram
            if ($status->get('email_manager')) {
                $this->createEmailManager($pls, $status);
            }

            if ($status->get('email_user')) {
                $this->createEmailCustomer($pls, $status);
            }
        }

        return true;
    }

    public function createEmailManager(array $pls, msOrderStatus $status): void
    {
        $subject = $this->ms3->pdoTools->getChunk('@INLINE ' . $status->get('subject_manager'), $pls);
        $tpl = '';
        $chunk = $this->modx->getObject(modChunk::class, ['id' => $status->get('body_manager')]);
        if ($chunk) {
            $tpl = $chunk->get('name');
        }
        $body = $this->modx->runSnippet('msGetOrder', array_merge($pls, ['tpl' => $tpl]));
        $emails = array_map(
            'trim',
            explode(
                ',',
                $this->modx->getOption('ms3_email_manager', null, $this->modx->getOption('emailsender'))
            )
        );
        if (!empty($subject)) {
            foreach ($emails as $email) {
                $this->sendEmail($email, $subject, $body);
            }
        }
    }

    public function createEmailCustomer(array $pls, msOrderStatus $status): void
    {
        $pls['user_id'] = 1;
        $profile = $this->modx->getObject(modUserProfile::class, ['internalKey' => $pls['user_id']]);
        //TODO  сюда добавить email из msCustomer
        if ($profile) {
            $subject = $this->ms3->pdoTools->getChunk('@INLINE ' . $status->get('subject_user'), $pls);
            $tpl = '';
            if ($chunk = $this->modx->getObject(modChunk::class, ['id' => $status->get('body_user')])) {
                $tpl = $chunk->get('name');
            }
            $body = $this->modx->runSnippet('msGetOrder', array_merge($pls, ['tpl' => $tpl]));
            $email = $profile->get('email');
            $this->sendEmail($email, $subject, $body);
        }
    }

    public function sendEmail(string $email, string $subject, string $body): void
    {
        if (preg_match('#.*?@#', $email)) {
            if ($this->useScheduler && $this->schedulerTask instanceof \sTask) {
                $this->schedulerTask->schedule('+1 second', [
                    'email' => $email,
                    'subject' => $subject,
                    'body' => $body
                ]);
            } else {
                $this->ms3->utils->sendEmail($email, $subject, $body);
            }
        }
    }

    protected function preparePls($msOrder)
    {
        // TODO у разных получателей может быть разный язык. Не привязываться к языку заказа, если назначен язык получателя
        $lang = $this->getLang($msOrder);

        $this->modx->setOption('cultureKey', $lang);
        $this->modx->lexicon->load($lang . ':minishop3:default', $lang . ':minishop3:cart');

        $tv_list = $this->modx->getOption('ms3_order_tv_list', null, '');
        $pls = $msOrder->toArray();
        $pls['cost'] = $this->ms3->format->price($pls['cost']);
        $pls['cart_cost'] = $this->ms3->format->price($pls['cart_cost']);
        $pls['delivery_cost'] = $this->ms3->format->price($pls['delivery_cost']);
        $pls['weight'] = $this->ms3->format->weight($pls['weight']);
        $pls['payment_link'] = '';
        if (!empty($tv_list)) {
            $pls['includeTVs'] = $tv_list;
        }
        $msPayment = $msOrder->getOne('Payment');
        if ($msPayment) {
            //TODO реализовать загрузку классов
            //$pls['payment_link'] = $this->getPaymentLink($msPayment, $msOrder);
        }

        return $pls;
    }

    protected function getLang($msOrder)
    {
        $lang = $this->modx->getOption('cultureKey', null, 'en', true);
        $tmp = $this->modx->getObject(
            modUserSetting::class,
            ['key' => 'cultureKey', 'user' => $msOrder->get('user_id')]
        );
        if ($tmp) {
            $lang = $tmp->get('value');
        } else {
            $tmp = $this->modx->getObject(
                modContextSetting::class,
                ['key' => 'cultureKey', 'context_key' => $msOrder->get('context')]
            );
            if ($tmp) {
                $lang = $tmp->get('value');
            }
        }
        // TODO реализовать запись и проверку языка заказа в самом объекте заказа

        return $lang;
    }

    protected function getPaymentLink($msPayment, $msOrder)
    {
        $class = $msPayment->get('class');
        if (!empty($class)) {
            $this->ms3->loadCustomClasses('payment');
            if (class_exists($class)) {
                /** @var Payment $controller */
                $controller = new $class($msOrder);
                if (method_exists($controller, 'getPaymentLink')) {
                    return $controller->getPaymentLink($msOrder);
                }
            }
        }

        return '';
    }

    protected function setSchedulerTask(): void
    {
        $this->useScheduler = false;
        if ($this->modx->services->has('scheduler')) {
            /** @var \Scheduler $scheduler */
            $scheduler = $this->modx->services->get('scheduler');
            $this->schedulerTask = $scheduler->getTask('minishop3', 'ms3_send_email');
            if (!$this->schedulerTask) {
                $this->schedulerTask = $this->createEmailTask();
            }
        }
    }

    /**
     * Creating Scheduler's task for sending email
     */
    protected function createEmailTask(): false|object|null
    {
        $task = $this->modx->newObject(\sFileTask::class);
        $task->fromArray([
            'class_key' => 'sFileTask',
            'content' => 'elements/tasks/sendEmail.php',
            'namespace' => 'minishop3',
            'reference' => 'ms3_send_email',
            'description' => 'MiniShop3 Email'
        ]);
        if (!$task->save()) {
            return false;
        }
        return $task;
    }
}
