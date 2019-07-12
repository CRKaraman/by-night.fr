<?php

namespace App\Command;

use App\Fetcher\EventFetcher;
use App\Parser\ParserInterface;
use App\Producer\EventProducer;
use LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsImportCommand extends AppCommand
{
    /**
     * @var EventFetcher
     */
    private $eventFetcher;

    /**
     * @var EventProducer
     */
    private $eventProducer;

    /** @var ParserInterface[] */
    private $parsers;

    public function __construct(EventFetcher $eventFetcher, EventProducer $eventProducer, array $parsers)
    {
        $this->eventFetcher = $eventFetcher;
        $this->eventProducer = $eventProducer;
        $this->parsers = $parsers;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:events:import')
            ->setDescription('Ajouter / mettre à jour des nouveaux événements sur By Night')
            ->addArgument('parser', InputArgument::REQUIRED, 'Nom du service à executer');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parser = $input->getArgument('parser');
        if (empty($this->parsers[$parser])) {
            throw new LogicException(\sprintf(
                'Le parser "%s" est introuvable',
                $parser
            ));
        }

        $service = $this->parsers[$parser];
        if (!$service instanceof ParserInterface) {
            throw new LogicException(\sprintf(
                'Le service "%s" doit être une instance de ParserInterface',
                $service
            ));
        }

        $events = $this->eventFetcher->fetchEvents($service);
        foreach ($events as $event) {
            $this->eventProducer->scheduleEvent($event);
        }
    }
}
