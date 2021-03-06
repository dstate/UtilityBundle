<?php

namespace NyroDev\UtilityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Symfony2 command to update confidentielles tags.
 */
class XlsTranslationsCommand extends ContainerAwareCommand
{
    /**
     * Configure the command.
     */
    protected function configure()
    {
        $this
            ->setName('nyrodev:xlsTranslations')
            ->setDescription('Fill XLS file with translations')
            ->addArgument('dest', InputArgument::REQUIRED, 'Destination file')
            ->addArgument('dir', InputArgument::OPTIONAL, 'Directory to search in', 'src')
            ->addArgument('suffix', InputArgument::OPTIONAL, 'Translation file suffix', '')
            ->addArgument('extension', InputArgument::OPTIONAL, 'Translation file extension', 'yml');
    }

    protected $existing;
    protected $className;
    protected $accessor;

    /**
     * Executes the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getContainer()->get('nyrodev')->increasePhpLimits();
        $dest = $input->getArgument('dest');
        $dir = $input->getArgument('dir');
        $suffix = $input->getArgument('suffix');
        $extension = $input->getArgument('extension');

        $locale = $this->getContainer()->getParameter('locale');
        $locales = $this->getContainer()->hasParameter('locales') ? explode('|', $this->getContainer()->getParameter('locales')) : array();
        if (count($locales) == 0) {
            $output->writeln('locales is not configured or empty, exiting');

            return;
        }
        unset($locales[array_search($locale, $locales)]);

        $output->writeln('Search for original translations files');
        $originals = array();
        $finder = new Finder();
        $translations = $finder
                    ->directories()
                    ->in('./'.$dir)
                    ->name('translations');

        foreach ($translations as $translation) {
            $finderTr = new Finder();
            $files = $finderTr->files()->in($translation->getRealpath())->name('*.'.$locale.$suffix.'.'.$extension);
            foreach ($files as $file) {
                $originals[] = $file->getRealpath();
            }
        }

        $nbO = count($originals);
        if ($nbO) {
            $cols = array_merge(array(
                'domain',
                'ident',
                'translation',
            ), $locales);

            if (!file_exists($dest)) {
                $phpExcel = new \PHPExcel();
                $title = $creator = 'Translations';
                $phpExcel->getProperties()->setCreator($creator)
                                ->setLastModifiedBy($creator)
                                ->setTitle($title)
                                ->setSubject($title);
                $sheet = $phpExcel->setActiveSheetIndex(0);
                $sheet->setTitle($title);

                $row = 1;
                $col = 0;
                foreach ($cols as $field) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $field);
                    $sheet->getStyleByColumnAndRow($col, $row)->getFont()->setBold(true);
                    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
                    ++$col;
                }
                ++$row;
            } else {
                $fileType = \PHPExcel_IOFactory::identify($dest);
                $objReader = \PHPExcel_IOFactory::createReader($fileType);

                $phpExcel = $objReader->load($dest);
                $sheet = $phpExcel->getActiveSheet();

                $row = $sheet->getHighestDataRow() + 1;
            }

            $output->writeln('Parsing '.$nbO.' translation files');
            $nbTrunc = -2 - strlen($extension) - strlen($locale) - strlen($suffix);
            $fs = new Filesystem();

            $foundTr = array();
            $defLangs = array(
                $locale => null,
            );
            foreach ($locales as $loc) {
                $defLangs[$loc] = null;
            }

            foreach ($originals as $original) {
                $output->writeln('Start fetching '.$original);
                $domain = substr(basename($original), 0, $nbTrunc);
                $dir = dirname($original);

                $trans = $this->flattenTrans(Yaml::parse(file_get_contents($original)));
                foreach ($trans as $k => $v) {
                    $foundTr[$k] = array_merge($defLangs, array($locale => $v));
                }

                foreach ($locales as $loc) {
                    $default = $dir.'/'.$domain.'.'.$loc.$suffix.'.'.$extension;
                    if ($fs->exists($default)) {
                        $trans = $this->flattenTrans(Yaml::parse(file_get_contents($default)));
                        foreach ($trans as $k => $v) {
                            if (isset($foundTr[$k])) {
                                $foundTr[$k][$loc] = $v;
                            } else {
                                $foundTr[$k] = array_merge($defLangs, array($loc => $v));
                            }
                        }
                    }
                }

                foreach ($foundTr as $ident => $trans) {
                    $col = 0;
                    $sheet->setCellValueByColumnAndRow($col, $row, $domain);
                    ++$col;
                    $sheet->setCellValueByColumnAndRow($col, $row, $ident);
                    ++$col;
                    $sheet->setCellValueByColumnAndRow($col, $row, $trans[$locale]);
                    ++$col;
                    foreach ($locales as $loc) {
                        $sheet->setCellValueByColumnAndRow($col, $row, $trans[$loc]);
                        ++$col;
                    }
                    ++$row;
                }
            }

            $sheet->calculateColumnWidths();

            $objWriter = \PHPExcel_IOFactory::createWriter($phpExcel, 'Excel5');
            $objWriter->save($dest);

            $output->writeln('XLS file saved: '.$dest);
        } else {
            $output->writeln('No original translation files found.');
        }
    }

    protected function flattenTrans(array $trans, $prefix = null)
    {
        $ret = array();
        if (!is_null($prefix)) {
            $prefix .= '.';
        }
        foreach ($trans as $k => $v) {
            $curPrefix = $prefix.$k;
            if (is_array($v)) {
                $ret += $this->flattenTrans($v, $curPrefix);
            } else {
                $ret[$curPrefix] = $v;
            }
        }

        return $ret;
    }
}
