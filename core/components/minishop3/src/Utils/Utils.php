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
     * Shorthand for original modX::invokeEvent() with normalized returnedValues handling.
     *
     * @param string $eventName
     * @param array<string, mixed> $params
     * @param string $glue
     *
     * @return array{success: bool, message: string, data: array<string, mixed>, values: array<string, mixed>}
     */
    public function invokeEvent($eventName, array $params = [], $glue = '<br/>')
    {
        EventGate::clearReturnedValues($this->modx);

        $response = $this->modx->invokeEvent($eventName, $params);
        $message = EventGate::normalizeMessage($response, $glue);
        $returnedValues = EventGate::getReturnedValues($this->modx);

        return EventGate::buildInvokeResult($params, $returnedValues, $message);
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
     */
    public function sendEmail(string $email, string $subject, string $body = ''): void
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

    /**
     * Extract option key from form field name (e.g. options-color[] → color)
     *
     * @param string $key Form field key (options-{key} or options-{key}[])
     * @return string|null Option key or null if not an options field
     */
    public static function extractOptionKey(string $key): ?string
    {
        if (!str_starts_with($key, 'options-')) {
            return null;
        }
        $optionKey = rtrim(substr($key, 8), '[]');
        return $optionKey !== '' ? $optionKey : null;
    }

    /**
     * Decode an option value coming from the product form.
     *
     * Multi-value option types (comboMultiple, comboColors, comboOptions) post their value as a
     * JSON-encoded array in a single hidden input, because ExtJS BasicForm.getValues() reads only
     * the last matching DOM node for a given name — which would drop all but one pick if we used
     * repeated name[] inputs. All other types post a scalar string or a native array. This helper
     * turns a JSON array string back into an array and leaves other shapes untouched.
     *
     * @param mixed $value Raw POST value
     * @return mixed Array if value was a JSON array string, otherwise the value unchanged.
     */
    public static function decodeOptionValue($value)
    {
        if (!is_string($value) || $value === '' || $value[0] !== '[') {
            return $value;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * Parse option values coming from CSV import.
     *
     * CSV import accepts the same JSON array format as the product form. For known multi-value
     * option types it also accepts a comma-separated cell value, matching the manager UI chips.
     *
     * @param mixed $value Raw CSV cell value
     * @param bool $isMultiValueType Whether the option type stores multiple values
     * @return mixed Parsed array for multi-value cells, otherwise scalar value unchanged
     */
    public static function parseImportedOptionValue($value, bool $isMultiValueType)
    {
        $value = self::decodeOptionValue($value);

        if (!$isMultiValueType || !is_string($value)) {
            return $value;
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn(string $item): bool => $item !== ''
        ));
    }

    public static function getVendorId($modx, $name)
    {
        $criteria = [
            'id' => $name,
            'OR:name:=' => $name
        ];

        $vendor = $modx->getObject('MiniShop3\Model\msVendor', $criteria);

        if ($vendor) {
            return $vendor->get('id');
        }

        $vendor = $modx->newObject('MiniShop3\Model\msVendor');
        $vendor->set('name', $name);
        $vendor->save();

        return $vendor->get('id');
    }
}
