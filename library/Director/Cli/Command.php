<?php

namespace Icinga\Module\Director\Cli;

use Icinga\Cli\Command as CliCommand;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Application\Config;

class Command extends CliCommand
{
    /** @var  Db */
    protected $db;

    /** @var  CoreApi */
    private $api;

    protected function renderJson($object, $pretty = true)
    {
        return json_encode($object, $pretty ? JSON_PRETTY_PRINT : null) . "\n";
    }

    /**
     * @param $json
     * @return mixed
     * @throws \Icinga\Exception\IcingaException
     */
    protected function parseJson($json)
    {
        $res = json_decode($json);

        if ($res === null) {
            $this->fail(sprintf(
                'Invalid JSON',
                $this->getLastJsonError()
            ));
        }

        return $res;
    }

    // TODO: just return json_last_error_msg() for PHP >= 5.5.0
    protected function getLastJsonError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                return 'The maximum stack depth has been exceeded';
            case JSON_ERROR_CTRL_CHAR:
                return 'Control character error, possibly incorrectly encoded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Invalid or malformed JSON';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'An error occured when parsing a JSON string';
        }
    }

    /**
     * @param null $endpointName
     * @return CoreApi|\Icinga\Module\Director\Core\LegacyDeploymentApi
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function api($endpointName = null)
    {
        if ($this->api === null) {
            if ($endpointName === null) {
                $endpoint = $this->db()->getDeploymentEndpoint();
            } else {
                $endpoint = IcingaEndpoint::load($endpointName, $this->db());
            }

            $this->api = $endpoint->api();
        }

        return $this->api;
    }

    /**
     * Raise PHP resource limits
     *
     * TODO: do this in a failsafe way, and only if necessary
     *
     * @return self;
     */
    protected function raiseLimits()
    {
        MemoryLimit::raiseTo('1024M');

        ini_set('max_execution_time', 0);
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            ini_set('zend.enable_gc', 0);
        }

        return $this;
    }

    /**
     * @return Db
     * @throws ConfigurationError
     */
    protected function db()
    {
        if ($this->db === null) {
            $resourceName = $this->params->get('dbResourceName');

            if ($resourceName === null) {
                // Hint: not using $this->Config() intentionally. This allows
                // CLI commands in other modules to use this as a base class.
                $resourceName = Config::module('director')->get('db', 'resource');
            }
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                throw new ConfigurationError('Director is not configured correctly');
            }
        }

        return $this->db;
    }
}
