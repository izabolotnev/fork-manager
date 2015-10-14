# fork-manager

Simple fork manager. It can run tasks once or run it again and again. Good for processing a queues and other schedule
purposes. 

## Requirements

- PHP 5.4 or later
- PCNTL extension

## Installation

The recommended installation method for this library is by adding the
dependency to your [composer.json][composer].

```json
{
	"require": {
		"izabolotnev/fork-manager": "~1.0"
	}
}
```

## Release notes

* 1.0.0 (2015-10-14, initial release)
 - Added the `ForkManager` class
 - Added the `Task` class

## Usage

```php
class SampleTask extends \izabolotnev\Task
{

    protected $expectedIterations;

    protected $remainderIterations;

    protected $verbose = false;

    /**
     * @var bool Switch to true if intercepted SIGTERM
     */
    protected $isStopping = false;

    /**
     * @param int  $iterations
     * @param bool $verbose
     */
    public function __construct($iterations, $verbose = false)
    {
        $this->expectedIterations  = max($iterations, 0);
        $this->remainderIterations = $this->expectedIterations;
        $this->verbose             = (bool)$verbose;
    }

    protected function afterFork()
    {
        pcntl_signal(SIGTERM, [$this, 'handlerSigTerm']);

        // For test purposes call handlerSigTerm after Ctrl+C
        pcntl_signal(SIGINT, [$this, 'handlerSigTerm']);
    }

    /**
     * Handler for the SIGTERM signal.
     * Gracefull exit
     */
    public function handlerSigTerm()
    {
        $this->remainderIterations = min($this->expectedIterations, 5);
        $this->isStopping          = true;

        $this->verbose && fputs(\STDOUT, 'Gracefull stop' . PHP_EOL);
    }

    /**
     * Handler for the SIGINT signal/
     * Exit immediately
     */
    public function handlerSigInt()
    {
        $this->expectedIterations = 0;
        $this->isStopping         = true;

        $this->beforeExit();

        exit(0);
    }

    /**
     * @return int
     */
    public function process()
    {
        $date = new \DateTime();

        $pid = getmypid();
        $this->verbose && fputs(\STDOUT, sprintf('Process #%s' . PHP_EOL, $pid));

        $i = 1;

        while ($this->remainderIterations-- > 0) {
            $this->verbose && fputs(\STDOUT, sprintf(
                "Process #%d | %'.03d/%d \033[%sm%s\033[0m" . PHP_EOL,
                $pid,
                $i++,
                $this->expectedIterations,
                $this->isStopping ? '0;33' : '0;32',
                $date->modify('+1 second')->format('Y-m-d H:i:s')
            ));

            sleep(1);
        }

        return 0;
    }
}

$task        = new SampleTask(100, true);
$forkManager = new \izabolotnev\ForkManager($task, 2, [\izabolotnev\ForkManager::DEBUG => true]);

$forkManager->once();
```

## License

[GNU General Public License 2.0 or later][license].

[composer]: https://getcomposer.org/
[license]: https://www.gnu.org/copyleft/gpl.html
