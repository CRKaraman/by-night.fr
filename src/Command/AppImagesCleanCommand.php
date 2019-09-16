<?php

namespace App\Command;

use App\Cleaner\ImageCleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AppImagesCleanCommand extends Command
{
    private $imageCleaner;

    /**
     * {@inheritdoc}
     */
    public function __construct(ImageCleaner $imageCleaner)
    {
        parent::__construct();

        $this->imageCleaner = $imageCleaner;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('app:images:clean')
            ->setDescription('Supprime les images inutilisées sur le serveur')
            ->addOption('dry', 'dry-run', InputOption::VALUE_NONE, 'Active le monitor des fonctions');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->imageCleaner->clean($input->getOption('dry'));
    }
}
