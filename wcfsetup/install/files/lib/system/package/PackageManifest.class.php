<?php
namespace wcf\system\package;

/**
 * Generates a manifest for the given PackageArchive.
 * 
 * The manifest is a structured representation of the functional parts
 * of the archive wherein changes could be relevant to security.
 * 
 * @author	Tim Duesterhus
 * @copyright	2001-2020 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\System\Package
 * @since	5.3
 */
final class PackageManifest {
	/**
	 * @var	PackageArchive
	 */
	protected $archive;
	
	const SUPPORTED_VERSIONS = [1];
	const CURRENT_VERSION = 1;
	
	public function __construct(PackageArchive $archive) {
		$this->archive = $archive;
	}
	
	/**
	 * Returns a string containing the manifest version and the manifest's hash.
	 *
	 * @param int $version An item in self::SUPPORTED_VERSIONS.
	 * @return string
	 */
	public function getHash($version = self::CURRENT_VERSION) {
		if ($version === 1) {
			return $version.'-'.hash('sha256', $this->getManifest($version));
		}
		else if (in_array($version, self::SUPPORTED_VERSIONS)) {
			throw new \LogicException("Unhandled, but supported, manifest version '" . $version . "'");
		}
		else {
			throw new \InvalidArgumentException("Unknown manifest version '" . $version . "'");
		}
	}
	
	/**
	 * Returns the archive's manifest.
	 * 
	 * @param int $version An element of self::SUPPORTED_VERSIONS.
	 * @return string
	 */
	public function getManifest($version = self::CURRENT_VERSION) {
		if ($version === 1) {
			return $this->getManifestV1();
		}
		else if (in_array($version, self::SUPPORTED_VERSIONS)) {
			throw new \LogicException("Unhandled, but supported, manifest version '" . $version . "'");
		}
		else {
			throw new \InvalidArgumentException("Unknown manifest version '" . $version . "'");
		}
	}
	
	private function getManifestV1() {
		$requirements = array_map(function ($requirement) {
			return $requirement['file'];
		}, $this->archive->getRequirements());

		$optionals = array_map(function ($optional) {
			if (empty($optional['file'])) {
				throw new \UnexpectedValueException('Expected to see a file="" attribute for an optional.');
			}
			
			return $optional['file'];
		}, $this->archive->getOptionals());
		
		$includedPackages = array_merge($requirements, $optionals);
		$ignoredFiles = array_merge($includedPackages, ['package.xml']);
		
		return $this->stringifyV1([
			'manifestVersion' => '1',
			'identifier' => $this->archive->getPackageInfo('name'),
			'version' => $this->archive->getPackageInfo('version'),
			'isApplication' => $this->archive->getPackageInfo('isApplication'),
			'humanName' => $this->getHumanNames(),
			'requirements' => $this->getRequirements(),
			'excludedPackages' => $this->getExcludedPackages(),
			'files' => $this->getFiles($ignoredFiles),
			'install' => $this->getInstallInstructions(),
			'update' => $this->getUpdateInstructions(),
		]);
	}
	
	private function getHumanNames() {
		$humanNames = $this->archive->getPackageInfo('packageName');
		ksort($humanNames);
		return $humanNames;
	}
	
	private function getRequirements() {
		$requirements = $this->archive->getRequirements();
		
		usort($requirements, function ($a, $b) {
			return $a['name'] <=> $b['name'];
		});
		
		return array_map(function ($requirementData) {
			unset($requirementData['file']);
			ksort($requirementData);
			return $requirementData;
		}, $requirements);
	}
	
	private function getExcludedPackages() {
		$exclusions = $this->archive->getExcludedPackages();
		
		usort($exclusions, function ($a, $b) {
			return $a['name'] <=> $b['name'];
		});
		
		return array_map(function ($exclusionData) {
			ksort($exclusionData);
			return $exclusionData;
		}, $exclusions);
	}
	
	private function getFiles($ignore = []) {
		$tar = $this->archive->getTar();
		$files = [];
		foreach ($tar->getContentList() as $file) {
			if ($file['type'] !== 'file') continue;
			if (in_array($file['filename'], $ignore, true)) continue;
			$files[$file['filename']] = hash('sha256', $tar->extractToString($file['index']));
		}
		ksort($files);
		return $files;
	}
	
	private function getInstallInstructions() {
		return $this->cleanInstructions($this->archive->getInstallInstructions());
	}
	
	private function getUpdateInstructions() {
		$updateInstructions = $this->archive->getUpdateInstructions();
		ksort($updateInstructions);
		return array_map([$this, 'cleanInstructions'], $updateInstructions);
	}
	
	private function cleanInstructions(array $instructions) {
		// Note: The $instructions array *must not* be sorted. The order
		// of instructions is important!
		return array_map(function ($instruction) {
			unset($instruction['attributes']['type']);
			ksort($instruction['attributes']);
			return [
				'type' => $instruction['pip'],
				'value' => $instruction['value'],
				'attributes' => $instruction['attributes'],
			];
		}, $instructions);
	}
	
	/**
	 * Transforms the given $data into a stable, unique string representation.
	 * 
	 * The method takes care to return the same string for identical input data and
	 * to return different strings for differing input data.
	 * 
	 * Attention: This method must not be modified. If a format change is required a
	 * replacement method must be written.
	 * 
	 * @param mixed $data
	 * @param int $depth
	 * @return string
	 * @throws UnexpectedValueException On non-representable data.
	 */
	private function stringifyV1($data, $depth = 0) {
		if (!is_array($data)) {
			return str_repeat('  ', $depth)."'".$this->escape($data)."'\n";
		}
		if (empty($data)) {
			return str_repeat('  ', $depth)."[]\n";
		}
		
		$result = "";
		$numeric = null;
		$lastNumeric = -1;
		foreach ($data as $key => $value) {
			if (is_numeric($key)) {
				if ($numeric !== null && !$numeric) {
					throw new \UnexpectedValueException('Arrays with mixed numeric / string keys are not supported.');
				}
				if ($lastNumeric !== ($key - 1)) {
					throw new \UnexpectedValueException('Arrays with non-sequential numeric keys are not supported.');
				}
				
				$numeric = true;
				$lastNumeric = intval($key);
				$result .= str_repeat('  ', $depth)."- ";
			}
			else {
				if ($numeric !== null && $numeric) {
					throw new \UnexpectedValueException('Arrays with mixed numeric / string keys are not supported.');
				}
				
				$numeric = false;
				$result .= str_repeat('  ', $depth)."'".$this->escape($key)."':";
			}
			if (is_array($value) && !empty($value)) {
				$result .= "\n".$this->stringifyV1($value, $depth + 1);
			}
			else {
				$result .= " ".$this->stringifyV1($value);
			}
		}
		
		return $result;
	}
	
	private function escape($v) {
		return preg_replace_callback('/[^a-zA-Z0-9 \/\._\*\-]/', function ($matches) {
			return '\x'.bin2hex($matches[0]);
		}, $v);
	}
}
