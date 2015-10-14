<?php

namespace izabolotnev;

/**
 * Class ForkManager
 *
 * @author Ilya Zabolotnev <gmail@zabolotnev.com>
 * @package izabolotnev
 */
class ForkManager
{

    const OPTION_MAX_EXECUTION_TIME = 'max_execution_time';

    const OPTION_DEBUG = 'debug';

    /**
     * @var Task
     */
    protected $task;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * Number of forks
     *
     * @var int
     */
    protected $processNum = 1;

    /**
     * Pid of children process
     *
     * @var array
     */
    protected $children = [];

    /**
     * @var array
     */
    protected $options = [
        self::OPTION_MAX_EXECUTION_TIME => 60,
        self::OPTION_DEBUG              => false
    ];

    /**
     * @var bool
     */
    protected $doWork = true;

    /**
     * If the call was interrupted by a signal, sleep() returns a non-zero value. On Windows, this value will always be
     * 192 (the value of the WAIT_IO_COMPLETION constant within the Windows API). On other platforms, the return value
     * will be the number of seconds left to sleep.
     *
     * @var int
     */
    protected $sleep = 0;

    /**
     * @param Task  $task
     * @param int   $processNum
     * @param array $options
     */
    public function __construct(Task $task, $processNum = 1, array $options = [])
    {
        $this->processNum = $processNum;
        $this->task       = $task;

        $this->options = array_merge($this->options, $options);
        $this->debug   = $this->options[self::OPTION_DEBUG];

        \pcntl_signal(SIGINT, [$this, 'handlerSigInt']);
        \pcntl_signal(SIGCHLD, [$this, 'handlerSigChild']);
        \pcntl_signal(SIGTERM, [$this, 'handlerSigTerm']);

        if ($this->options[self::OPTION_MAX_EXECUTION_TIME] > 0) {
            $this->debug && fputs(STDERR, sprintf(
                'All task must be finished after %d seconds' . PHP_EOL,
                $this->options[self::OPTION_MAX_EXECUTION_TIME]
            ));

            \pcntl_signal(SIGALRM, [$this, 'handlerSigAlarm']);
            \pcntl_alarm($this->options[self::OPTION_MAX_EXECUTION_TIME]);
        }
    }

    public function forever()
    {
        while ($this->doWork) {
            $this->makeForks();

            $this->sleep = sleep(1);
        }

        $this->debug && fputs(STDERR, sprintf('Process #%d is finished' . PHP_EOL, \getmypid()));
    }

    public function once()
    {
        $this->makeForks();

        while ($this->children) {
            $this->sleep = sleep(1);
        }

        $this->debug && fputs(STDERR, sprintf('Process #%d is finished' . PHP_EOL, \getmypid()));
    }

    protected function makeForks()
    {
        $left = $this->processNum - count($this->children);

        while ($left > 0) {
            if (0 === ($pid = \pcntl_fork())) {
                $this->debug && fputs(STDERR, sprintf('Process #%d is started' . PHP_EOL, \getmypid()));

                $status = $this->task->run();

                exit($status);
            } else {
                $this->children[] = $pid;

                $left--;
            }
        }
    }

    /**
     * The SIGINT signal is sent to a process by its controlling terminal when a user wishes to interrupt the process.
     * This is typically initiated by pressing Control-C
     */
    public function handlerSigInt()
    {
        $this->debug && fputs(STDERR, sprintf('Process %d intercepted SIGINT' . PHP_EOL, getmypid()));

        \pcntl_alarm(0);

        $this->stop(SIGINT);
    }

    /**
     * The SIGCHLD signal is sent to a process when a child process terminates, is interrupted, or resumes after being
     * interrupted.
     */
    public function handlerSigChild()
    {
        $this->debug && fputs(STDERR, sprintf('Process %d intercepted SIGCHLD' . PHP_EOL, getmypid()));

        while (($pid = \pcntl_wait($status, WNOHANG)) > 0) {
            $this->children = array_diff($this->children, [$pid]);

            $this->debug && fputs(STDERR, sprintf('Process #%d is finished' . PHP_EOL, $pid));
        }

        $this->sleep = sleep($this->sleep);
    }

    /**
     * The SIGTERM signal is sent to a process to request its termination. Unlike the SIGKILL signal, it can be caught
     * and interpreted or ignored by the process.
     */
    public function handlerSigTerm()
    {
        $this->debug && fputs(STDERR, sprintf('Process %d intercepted SIGTERM' . PHP_EOL, getmypid()));

        pcntl_alarm(0);

        $this->stop(SIGTERM);
    }

    /**
     * The SIGALRM signal is sent to a process when the time limit specified in a call to a preceding alarm setting
     * function (such as setitimer) elapses. SIGALRM is sent when real or clock time elapses.
     */
    public function handlerSigAlarm()
    {
        $this->debug && fputs(STDERR, sprintf('Process %d intercepted SIGALRM' . PHP_EOL, getmypid()));

        $this->stop(SIGTERM);
    }

    /**
     * Send signals to forks
     *
     * @param int $signal
     */
    protected function stop($signal)
    {
        $this->doWork = false;

        foreach ($this->children as $pid) {
            \posix_kill($pid, $signal);
        }
    }
}
