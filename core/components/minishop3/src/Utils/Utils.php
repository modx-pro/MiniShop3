<?php

namespace MiniShop3\Utils;

use MiniShop3\MiniShop3;
use MODX\Revolution\Mail\modMail;
use MODX\Revolution\Mail\modPHPMailer;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;

class Utils
{
    private $modx;
    private $ms3;

    public function __construct(MiniShop3 $ms3)
    {
        $this->ms3 = $ms3;
        $this->modx = $this->ms3->modx;
    }

    /**
     * General method to get JSON settings
     *
     * @param $key
     *
     * @return array|mixed
     */
    public function getSetting($key)
    {
        $setting = $this->modx->getObject(modSystemSetting::class, ['key' => $key]);
        if (!$setting) {
            $setting = $this->modx->newObject(modSystemSetting::class);
            $setting->set('key', $key);
            $setting->set('value', '[]');
            $setting->save();
        }

        $value = json_decode($setting->get('value'), true);
        if (!is_array($value)) {
            $value = [];
            $setting->set('value', $value);
            $setting->save();
        }

        return $value;
    }

    /**
     * General method to update JSON settings
     *
     * @param $key
     * @param $value
     */
    public function updateSetting($key, $value)
    {
        $setting = $this->modx->getObject(modSystemSetting::class, ['key' => $key]);
        if (!$setting) {
            $setting = $this->modx->newObject(modSystemSetting::class);
            $setting->set('key', $key);
        }
        $setting->set('value', json_encode($value));
        $setting->save();
    }

    /**
     * Shorthand for original modX::invokeEvent() method with some useful additions.
     *
     * @param $eventName
     * @param array $params
     * @param $glue
     *
     * @return array
     */
    public function invokeEvent($eventName, array $params = [], $glue = '<br/>')
    {
        if (isset($this->modx->event->returnedValues)) {
            $this->modx->event->returnedValues = null;
        }

        $response = $this->modx->invokeEvent($eventName, $params);
        if (is_array($response) && count($response) > 1) {
            foreach ($response as $k => $v) {
                if (empty($v)) {
                    unset($response[$k]);
                }
            }
        }

        $message = is_array($response) ? implode($glue, $response) : trim((string)$response);
        if (isset($this->modx->event->returnedValues) && is_array($this->modx->event->returnedValues)) {
            $params = array_merge($params, $this->modx->event->returnedValues);
        }

        return [
            'success' => empty($message),
            'message' => $message,
            'data' => $params,
        ];
    }

    /**
     * This method returns an error of the order
     *
     * @param string $message A lexicon key for error message
     * @param array $data .Additional data, for example cart status
     * @param array $placeholders Array with placeholders for lexicon entry
     *
     * @return array|string $response
     */
    public function error($message = '', $data = [], $placeholders = [])
    {
        return [
            'success' => false,
            'message' => $this->modx->lexicon($message, $placeholders),
            'data' => $data,
        ];

//        return $this->ms3->config['json_response']
//            ? json_encode($response)
//            : $response;
    }

    /**
     * This method returns a success of the order
     *
     * @param string $message A lexicon key for success message
     * @param array $data .Additional data, for example cart status
     * @param array $placeholders Array with placeholders for lexicon entry
     *
     * @return array|string $response
     */
    public function success($message = '', $data = [], $placeholders = [])
    {
        return [
            'success' => true,
            'message' => $this->modx->lexicon($message, $placeholders),
            'data' => $data,
        ];
//        return $this->ms3->config['json_response']
//            ? json_encode($response)
//            : $response;
    }

    /**
     * Shorthand for the call of processor
     *
     * @access public
     *
     * @param string $action Path to processor
     * @param array $data Data to be transmitted to the processor
     *
     * @return mixed The result of the processor
     */
    public function runProcessor($action = '', $data = [])
    {
        if (empty($action)) {
            return false;
        }
        $this->modx->error->reset();
//        $processorsPath = !empty($this->config['processorsPath'])
//            ? $this->config['processorsPath']
//            : MODX_CORE_PATH . 'components/minishop3/Processors/';

//        return $this->modx->runProcessor($action, $data, [
//            'processors_path' => $processorsPath,
//        ]);

        return $this->modx->runProcessor($action, $data);
    }

    /**
     * Pathinfo function for cyrillic files
     *
     * @param $path
     * @param string $part
     *
     * @return array
     */
    public function pathinfo($path, $part = '')
    {
        // Russian files
        if (preg_match('#[а-яё]#im', $path)) {
            $path = strtr($path, ['\\' => '/']);

            preg_match("#[^/]+$#", $path, $file);
            preg_match("#([^/]+)[.$]+(.*)#", $path, $file_ext);
            preg_match("#(.*)[/$]+#", $path, $dirname);

            $info = [
                'dirname' => (isset($dirname[1]))
                    ? $dirname[1]
                    : '.',
                'basename' => $file[0],
                'extension' => (isset($file_ext[2]))
                    ? $file_ext[2]
                    : '',
                'filename' => (isset($file_ext[1]))
                    ? $file_ext[1]
                    : $file[0],
            ];
        } else {
            $info = pathinfo($path);
        }

        return !empty($part) && isset($info[$part])
            ? $info[$part]
            : $info;
    }

    /**
     * Function for sending email
     *
     * @param string $email
     * @param string $subject
     * @param string $body
     *
     * @return void
     */
    public function sendEmail($email, $subject, $body = '')
    {
        $this->modx->getParser()->processElementTags('', $body, true, false, '[[', ']]', [], 10);
        $this->modx->getParser()->processElementTags('', $body, true, true, '[[', ']]', [], 10);

        $mail = new modPHPMailer($this->modx);
        $mail->setHTML(true);

        $mail->address('to', trim($email));
        $mail->set(modMail::MAIL_SUBJECT, trim($subject));
        $mail->set(modMail::MAIL_BODY, $body);
        $mail->set(modMail::MAIL_FROM, $this->modx->getOption('emailsender'));
        $mail->set(modMail::MAIL_FROM_NAME, $this->modx->getOption('site_name'));
        if (!$mail->send()) {
            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                'An error occurred while trying to send the email: ' . $mail->mailer->ErrorInfo
            );
        }
        $mail->reset();
    }

    public static function getVendorId($modx, $name)
    {
        $criteria = [
            'id' => $name,
            'OR:name:=' => $name
        ];

        $vendor = $modx->getObject('msVendor', $criteria);

        if ($vendor) {
            return $vendor->get('id');
        }

        $vendor = $modx->newObject('msVendor');
        $vendor->set('name', $name);
        $vendor->save();

        return $vendor->get('id');
    }
}
