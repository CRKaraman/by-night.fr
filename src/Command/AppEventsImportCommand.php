<?php

namespace App\Command;

use App\Parser\ParserInterface;
use App\Utils\Monitor;
use LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsImportCommand extends AppCommand
{
    /** @var ParserInterface[] */
    private $parsers;

    public function __construct(array $parsers)
    {
        parent::__construct();

        $this->parsers = $parsers;
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
            ->addArgument('parser', InputArgument::REQUIRED, 'Nom du service à executer')
            ->addOption('full', 'f', InputOption::VALUE_OPTIONAL, 'Effectue un full import du catalogue');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parserName = $input->getArgument('parser');
        if (empty($this->parsers[$parserName])) {
            throw new LogicException(\sprintf(
                'Le parser "%s" est introuvable',
                $parserName
            ));
        }

        $parser = $this->parsers[$parserName];

        Monitor::writeln(\sprintf(
            'Lancement de <info>%s</info>',
            $parser::getParserName()
        ));

        $parser->parse(! $input->hasOption('full'));
        $nbEvents = $parser->getParsedEvents();

            Monitor::writeln(\sprintf(
            '<info>%d</info> événements parsés',
            $nbEvents
        ));
    }
}
