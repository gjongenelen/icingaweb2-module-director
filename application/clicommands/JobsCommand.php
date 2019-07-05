<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Job\JobRunner;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Application\Logger;
use Exception;

class JobsCommand extends Command
{
    public function runAction()
    {
        $forever = $this->params->shift('forever');
        if (! $forever && $this->params->getStandalone() === 'forever') {
            $forever = true;
            $this->params->shift();
        }

        $jobId = $this->params->shift();
        if ($jobId) {
            $this->raiseLimits();
            $job = DirectorJob::loadWithAutoIncId($jobId, $this->db());
            $job->run();
            exit(0);
        }

        if ($forever) {
            $this->runforever();
        } else {
            $this->runAllPendingJobs();
        }
    }

    protected function runforever()
    {
        while (true) {
            $this->runAllPendingJobs();
            
            sleep(2);
        }
    }

    protected function runAllPendingJobs()
    {
        $jobs = new JobRunner($this->db());

        try {
            if ($this->hasBeenDisabled()) {
                return;
            }

            $jobs->runPendingJobs();
        } catch (Exception $e) {
            Logger::error('Director Job Error: ' . $e->getMessage());
            sleep(10);
        }
    }

    protected function hasBeenDisabled()
    {
        return $this->db()->settings()->disable_all_jobs === 'y';
    }
}
