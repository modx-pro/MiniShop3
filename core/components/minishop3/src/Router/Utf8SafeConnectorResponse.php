<?php

namespace MiniShop3\Router;

use MODX\Revolution\modConnectorResponse;
use MODX\Revolution\modX;

/**
 * Shared JSON exit for every MiniShop3 processor on connector.php (#689).
 *
 * Decision: sanitize at the connector response, not per-processor and not by
 * abandoning the legacy path. New processors inherit this automatically because
 * connector.php installs this class before handleRequest().
 *
 * Keep the control flow aligned with MODX 3 modConnectorResponse::outputContent().
 */
class Utf8SafeConnectorResponse extends modConnectorResponse
{
    public function outputContent(array $options = [])
    {
        $modx = &$this->modx;
        $target = preg_replace('/[\.]{2,}/', '', htmlspecialchars((string) ($options['action'] ?? '')));

        $siteId = $this->modx->user->getUserToken($this->modx->context->get('key'));
        $isLogin = $target == 'Login' || $target == 'Security/Login';

        if (empty($siteId) && (!defined('MODX_REQP') || MODX_REQP === true)) {
            $this->responseCode = 401;
            $this->body = $modx->error->failure($modx->lexicon('access_denied'), ['code' => 401]);
        } elseif (!$isLogin && !isset($_SERVER['HTTP_MODAUTH']) && (!isset($_REQUEST['HTTP_MODAUTH']) || empty($_REQUEST['HTTP_MODAUTH']))) {
            $this->responseCode = 401;
            $this->body = $modx->error->failure($modx->lexicon('access_denied'), ['code' => 401]);
        } elseif (!$isLogin && isset($_SERVER['HTTP_MODAUTH']) && $_SERVER['HTTP_MODAUTH'] != $siteId) {
            $this->responseCode = 401;
            $this->body = $modx->error->failure($modx->lexicon('access_denied'), ['code' => 401]);
        } elseif (!$isLogin && isset($_REQUEST['HTTP_MODAUTH']) && $_REQUEST['HTTP_MODAUTH'] != $siteId) {
            $this->responseCode = 401;
            $this->body = $modx->error->failure($modx->lexicon('access_denied'), ['code' => 401]);
        } elseif (empty($options['action'])) {
            $this->responseCode = 404;
            $this->body = $this->modx->error->failure($modx->lexicon('action_err_ns'), ['code' => 404]);
        } else {
            if (!isset($_POST)) {
                $_POST = [];
            }
            if (!isset($_GET) || $isLogin) {
                $_GET = [];
            }
            $scriptProperties = array_merge($_GET, $_POST);
            if (isset($_FILES) && !empty($_FILES)) {
                $scriptProperties = array_merge($scriptProperties, $_FILES);
            }

            $response = $this->modx->runProcessor($target, $scriptProperties, $options);
            if (!$response) {
                $this->responseCode = 404;
                $this->body = $this->modx->error->failure($this->modx->lexicon('processor_err_nf', [
                    'target' => $target,
                ]));
            } else {
                $this->body = $response->getResponse();
            }
        }

        [$json, $failedClosed] = $this->encodeUtf8SafeBody($modx);
        if ($failedClosed) {
            $this->responseCode = 500;
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=UTF-8');
            $message = 'OK';
            if (array_key_exists($this->responseCode, $this->_responseCodes)) {
                $message = $this->_responseCodes[$this->responseCode];
            }
            header('Status: ' . $this->responseCode . ' ' . $message);
            header('Version: ' . $_SERVER['SERVER_PROTOCOL']);
        }
        if (is_array($this->header)) {
            foreach ($this->header as $header) {
                header($header);
            }
        }

        @session_write_close();
        if ($failedClosed) {
            http_response_code(500);
        }
        die($json);
    }

    /**
     * @param modX $modx
     * @return array{0: string, 1: bool}
     */
    private function encodeUtf8SafeBody(object $modx): array
    {
        if (is_array($this->body)) {
            return Response::encodeConnectorJson(
                Response::connectorProcessorEnvelope($this->body, $modx),
                $modx
            );
        }

        $raw = (string) $this->body;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [$raw, false];
        }

        return Response::encodeConnectorJson($decoded, $modx);
    }
}
