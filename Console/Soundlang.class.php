<?php
// vim: set ai ts=4 sw=4 ft=php:

// Namespace should be FreePBX\Console\Command
namespace FreePBX\Console\Command;

// Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
// Tables
use Symfony\Component\Console\Helper\Table;
// Process
use Symfony\Component\Process\Process;

use Symfony\Component\Console\Command\HelpCommand;

use Symfony\Component\Console\Question\ConfirmationQuestion;

#[\AllowDynamicProperties]
class Soundlang extends Command {
	protected function configure(){
		$this->setName('sounds')
			->setDescription(_('Sound Language Prompts'))
			->setDefinition([new InputOption('list', null, InputOption::VALUE_NONE, _('List of available packages.')), new InputOption('listglobal', null, InputOption::VALUE_NONE, _('List of installed packages and which is the default.')), new InputOption('install', null, InputOption::VALUE_REQUIRED, _('Installs the language pack that we specify.')), new InputOption('uninstall', null, InputOption::VALUE_REQUIRED, _('Uninstalls the language pack that we specify.')), new InputOption('global', null, InputOption::VALUE_REQUIRED, _('We define the default language.')), new InputOption('sync', null, InputOption::VALUE_NONE, _('Download the information of the latest version available online of the language packs.'))]);
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		set_time_limit(0);
		$this->soundlang = \FreePBX::create()->Soundlang;

		if($input->getOption('list')){
			$this->listSounds($input, $output);
			exit();
		}

		if($input->getOption('sync')){
			$this->syncPackagesInfo($input, $output);
			exit();
		}

		if($input->getOption('install')){
			$this->installSounds($input, $output);
			exit();
		}

		if($input->getOption('uninstall')){
			$this->uninstallSounds($input, $output);
			exit();
		}

		if($input->getOption('listglobal')){
			$this->listGlobalSounds($input, $output);
			exit();
		}

		if($input->getOption('global')){
			$this->setGlobal($input, $output);
			exit();
		}
	}

	private function setGlobal(InputInterface $input, OutputInterface $output) {
		$code = $input->getOption('global');
		$list = $this->soundlang->getLanguages();
		if(!isset($list[$code])) {
			$output->writeln("<error>"._("That is not a valid ID")."</error>");
			exit(4);
		}
		$this->soundlang->setLanguage($code);
		$output->writeln(sprintf(_("Successfully set default language to [%s], you will need to reload."), strtoupper((string) $code)));
		needreload();
	}

	private function listGlobalSounds(InputInterface $input, OutputInterface $output) {
		$rows = [];
  $list 	 = $this->soundlang->getLanguages();
		$default = $this->soundlang->getLanguage();
		foreach ($list as $key => $value)
		{
			$def = ($key == $default) ? "X" : "";
			$rows[] = [$key, $value, $def];
		}
		$table = new Table($output);
		$table->setHeaders(["ID", _("Language"), _("Default")]);
		$table->setRows($rows);
		$table->render();
	}

	private function uninstallSounds(InputInterface $input, OutputInterface $output) {
		$code = $input->getOption('uninstall');
		$list = $this->listValidLangCodes();
		if(!in_array($code,$list)) {
			$output->writeln("<error>"._("That is not a valid ID")."</error>");
			exit(4);
		}
		$output->write(sprintf(_("Uninstalling %s...")));
		$this->soundlang->uninstallLanguage($code);
		$output->writeln(_("Done"));
	}

	private function installSounds(InputInterface $input, OutputInterface $output) {
		$code = $input->getOption('install');
		$list = $this->listValidLangCodes();
		if(!in_array($code,$list)) {
			$output->writeln("<error>"._("That is not a valid ID")."</error>");
			exit(4);
		}
		$license = $this->soundlang->getLanguageLicense($code);
		if(is_string($license)) {
			$output->writeln($license);
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion(_('Accept License Agreement?')." [y/n]", false);

			if (!$helper->ask($input, $output, $question)) {
				exit;
			}
		}
		$output->write(sprintf(_("Installing %s..."), $code));
		$this->soundlang->installLanguage($code);
		$output->writeln(_("Done"));
	}

	private function listValidLangCodes() {
		$this->soundlang->getOnlinePackages();
		$packages = $this->soundlang->getPackages();
		$codes = [];
		foreach ($packages as $package) {
			if(in_array($package['language'],$codes)) {
				continue;
			}
			$codes[] = $package['language'];
		}
		return $codes;
	}

	private function syncPackagesInfo(InputInterface $input, OutputInterface $output) {
		$output->write(_("Sync info..."));
		$this->soundlang->getOnlinePackages();
		$output->writeln(_("Done"));
	}

	private function listSounds(InputInterface $input, OutputInterface $output)	 {
		if($input->getOption('sync')){
			$this->syncPackagesInfo($input, $output);
		}
		$packages 			= $this->soundlang->getPackages();
		$formats 			= $this->soundlang->getFormatPref();
		$languagenames 		= $this->soundlang->getLanguageNames();
		$languagelocations 	= $this->soundlang->getLocationNames();
		$languages 			= [];
		foreach ($packages as $package)
		{
			if (isset($languages[$package['language']]))
			{
				$language = $languages[$package['language']];
			}
			else
			{
				$parts = explode("_",(string) $package['language']);
				$language = ['code' 		 => $package['language'], 'author' 	 => $package['author'], 'authorlink' => $package['authorlink'], 'license' 	 => $package['license'], 'installed'  => true, 'lang' 		 => ['name' 	 => $languagenames[$parts[0]] ?? $parts[0], 'locale' => (isset($parts[1]) && isset($languagelocations[$parts[1]])) ? ($languagelocations[$parts[1]] ?? $parts[1]) : '']];
			}

			if (in_array($package['format'], $formats))
			{
				if (empty($package['installed']) || $package['installed'] < $package['version'])
				{
					$language['installed'] = false;
				}
			}
			$languages[$package['language']] = $language;
		}

		ksort($languages);

		$table = new Table($output);
		$table->setHeaders(["ID", _("Language"), _("Author"), _("Installed")]);
		$rows = [];
		foreach($languages as $item) {
			$rows[] = [$item['code'], $item['lang']['name'] . (!empty($item['lang']['locale']) ? ' - '.$item['lang']['locale'] : '') . " (".$item['code'].")", $item['author'], $item['installed'] ? 'X' : ''];
		}
		$table->setRows($rows);
		$table->render();
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function outputHelp(InputInterface $input, OutputInterface $output)	 {
		$help = new HelpCommand();
		$help->setCommand($this);
		return $help->run($input, $output);
	}
}
