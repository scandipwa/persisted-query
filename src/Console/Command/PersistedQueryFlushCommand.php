<?php
/**
 * ScandiPWA_PersistedQuery
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_PersistedQuery
 * @author      Ilja Lapkovskis <ilja@scandiweb.com | info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\PersistedQuery\Console\Command;


use ScandiPWA\PersistedQuery\Cache\Query;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PersistedQueryFlushCommand extends Command
{
    /**
     * @var Query
     */
    private $query;
    
    /**
     * PersistedQueryFlushCommand constructor.
     * @param Query       $query
     * @param string|null $name
     */
    public function __construct(
        Query $query,
        string $name = null
    )
    {
        $this->query = $query;
        parent::__construct($name);
    }
    
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('scandipwa:pq:flush')
            ->setDescription('Flush persisted query storage(Redis)');
        
        parent::configure();
    }
    
    /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->query->clean()) {
            $output->writeln('Persisted query caches flushed (redis + varnish)');
        }
    }
    
}
