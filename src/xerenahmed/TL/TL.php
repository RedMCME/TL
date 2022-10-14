<?php

declare(strict_types=1);

namespace xerenahmed\TL;

use Webmozart\PathUtil\Path;

class TL{
	/** @var array<string, array<string, string>> [language => [project => translation]]*/
	private array $translations = [];

	private string $defaultProject = "common";
	private string $defaultLanguage = "en";

	public static function init(?string $defaultProject = null, ?string $defaultLanguage = null): self{
		$instance = new self();
		if($defaultProject !== null){
			$instance->defaultProject = $defaultProject;
		}
		if($defaultLanguage !== null){
			$instance->defaultLanguage = $defaultLanguage;
		}
		return $instance;
	}

	public function load(string $path, ?string $prefix = null): void{
		$prefix ??= pathinfo($path, PATHINFO_FILENAME);

		$files = scandir($path);
		if($files === false){
			throw new \RuntimeException("Could not scan directory $path");
		}
		$files = array_filter($files, function(string $file) use ($path): bool{
			return pathinfo($path . "/" . $file, PATHINFO_EXTENSION) === "json";
		});
		$languages = [];
		foreach($files as $file){
			$filePath = Path::join($path, $file);
			$file_contents = file_get_contents($filePath);
			if($file_contents === false){
				throw new \RuntimeException("Could not read file $filePath");
			}

			$translations = json_decode($file_contents, true, flags: JSON_THROW_ON_ERROR);
			if (!is_array($translations)){
				throw new \RuntimeException("Translation data must be an json object");
			}

			$translations = $this->generateMapping($prefix . ".", $translations);
			$languages[str_replace(".json", "", $file)] = $translations;
		}
		foreach($languages as $language => $translations){
			if(!isset($this->translations[$language])){
				$this->translations[$language] = [];
			}
			$this->translations[$language] = array_merge($this->translations[$language], $translations);
		}
	}

	/**
	 * @param string[] $projects
	 *
	 * @return \Closure[]
	 */
	public function useTranslations(?string $lang, array $projects): array{
		return array_map(function(string $project) use ($lang): \Closure{
			return $this->useTranslation($lang, $project);
		}, $projects);
	}

	/**
	 * @return \Closure[]
	 */
	public function withDefault(?string $lang, string ...$prefixes): array{
		return $this->useTranslations($lang, array_merge([$this->defaultProject], $prefixes));
	}

	public function useTranslation(?string $lang = null, ?string $prefix = null): \Closure{
		$lang ??= $this->defaultLanguage;
		$prefix ??= $this->defaultProject;

		/**
		 * @param array<string, mixed> $params
		 */
		return function(string $key, array $params = []) use ($prefix, $lang): string{
			return $this->translate($prefix . '.' . $key, $params, $lang);
		};
	}

	public function translateKey(string $key, ?string $lang = null): ?string{
		$lang ??= $this->defaultLanguage;
		return $this->translations[$lang][$key] ?? null;
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function translate(string $key, array $params = [], ?string $lang = null, bool $resident = true): string{
		if (is_string($lang) && !array_key_exists($lang, $this->translations)){
			// convert en_US to en
			$lang = $this->getFitLangKey($lang, false);
		}

		if (is_null($lang) || !array_key_exists($lang, $this->translations)) {
			$lang = $this->defaultLanguage;
		}

		$applyParams = function(string $translation) use ($key, $params): string{
			$applied = false;
			foreach($params as $param => $value){
				if (!is_string($param)){
					continue;
				}

				$applied = true;
				$translation = str_replace("{{" . $param . "}}", strval($value), $translation);
			}

			if (!$applied){
				$translation = sprintf($translation, ...array_values($params));
			}
			return $translation;
		};

		$langTranslation = $this->translations[$lang][$key] ?? null;
		if($langTranslation !== null){
			return $applyParams($langTranslation);
		}

		$defaultLangTranslation = $this->translations[$this->defaultLanguage][$key] ?? null;
		if($defaultLangTranslation !== null){
			return $applyParams($defaultLangTranslation);
		}

		if ($resident) {
			return sprintf("%s.%s", $lang, $key);
		}

		throw new \RuntimeException("Translation not found for key $key");
	}

	/**
	 * @param array<string, string | array<string, string>> $translations
	 * @return array<string, string>
	 */
	public function generateMapping(string $prefix, array $translations): array{
		$mapping = [];
		foreach($translations as $key => $value){
			if (!is_string($key)){
				throw new \RuntimeException("Translation key must be a string");
			}
			if(is_array($value)){
				$mapping = array_merge($mapping, $this->generateMapping($prefix . $key . ".", $value));
			}elseif(is_string($value)){
				$mapping[$prefix . $key] = $value;
			} else {
				throw new \RuntimeException("Translation value must be a string or an array");
			}
		}
		return $mapping;
	}

	/** @return array<string, array<string, string>> */
	public function getAllTranslations(): array{
		return $this->translations;
	}

	/** @return string[] */
	public function getLanguages(): array{
		return array_keys($this->translations);
	}

	/** @return string[] */
	public function getLanguagesTranslated(?string $lang = null, ?string $project = null): array{
		$lang ??= $this->defaultLanguage;
		$project ??= $this->defaultProject;

		$translated = [];
		foreach($this->translations as $language => $translations){
			$translated[$language] = $this->translate($project . ".language.$language", [], $lang, false);
		}
		return $translated;
	}

	public function getFitLangKey(string $lang, bool $default = true): ?string{
		if (array_key_exists($lang, $this->translations)){
			return $lang;
		}
		$langParts = explode("_", $lang);
		if(count($langParts) > 1){
			$lang = $langParts[0];
			if (array_key_exists($lang, $this->translations)){
				return $lang;
			}
		}

		$langParts = explode("-", $lang);
		if(count($langParts) > 1){
			$lang = $langParts[0];
			if (array_key_exists($lang, $this->translations)){
				return $lang;
			}
		}

		if ($default) {
			return $this->defaultLanguage;
		}

		return null;
	}
}