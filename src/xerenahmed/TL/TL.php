<?php

declare(strict_types=1);

namespace xerenahmed\TL;

class TL{
	/** @var TLNamespace[] */
	private array $namespaces = [];

	private string $defaultNamespace = "common";
	private string $defaultLanguage = "en";

	public static function init(?string $defaultNamespace = null, ?string $defaultLanguage = null): self{
		$instance = new self();
		if($defaultNamespace !== null){
			$instance->defaultNamespace = $defaultNamespace;
		}
		if($defaultLanguage !== null){
			$instance->defaultLanguage = $defaultLanguage;
		}
		return $instance;
	}

	public function load(string $path, ?string $prefix = null): void{
		// list directories
		$dirs = scandir($path);
		if($dirs === false){
			throw new \RuntimeException("Could not scan directory $path");
		}

		$directories = array_filter($dirs, function(string $file) use ($path): bool{
			return is_dir($path . "/" . $file) && $file !== "." && $file !== "..";
		});
		foreach($directories as $directory){
			$namespace = new TLNamespace($directory);
			$namespace->load($path . "/" . $directory);
			if (!$namespace->isEmpty()){
				$namespacePath = $directory;
				if ($prefix !== null) {
					$namespacePath = $prefix . "." . $namespacePath;
				}
				$this->namespaces[$namespacePath] = $namespace;
			}
		}
		if(!isset($this->namespaces[$this->defaultNamespace])){
			throw new \RuntimeException("Default namespace not found");
		}
		if(is_null($this->namespaces[$this->defaultNamespace]->getLanguage($this->defaultLanguage))){
			throw new \RuntimeException("Default language not found");
		}
	}

	/**
	 * @param string[] $namespaces
	 *
	 * @return \Closure[]
	 */
	public function useTranslations(?string $lang, array $namespaces): array{
		return array_map(function(string $namespace) use ($lang): \Closure{
			return $this->useTranslation($lang, $namespace);
		}, $namespaces);
	}

	/**
	 * @return \Closure[]
	 */
	public function withDefaultNamespace(?string $lang, string ...$namespaces): array{
		return $this->useTranslations($lang, array_merge([$this->defaultNamespace], $namespaces));
	}

	public function useTranslation(?string $lang = null, ?string $namespace = null): \Closure{
		$namespace ??= $this->defaultNamespace;
		$lang ??= $this->defaultLanguage;

		if(!isset($this->namespaces[$namespace])){
			throw new \InvalidArgumentException("Namespace $namespace does not exist");
		}
		/**
		 * @param array<string, mixed> $params
		 */
		return function(string $key, array $params = []) use ($namespace, $lang): string{
			return $this->translate($key, $params, $lang, $namespace);
		};
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function translate(string $key, array $params = [], ?string $lang = null, ?string $namespace = null): string{
		$namespace ??= $this->defaultNamespace;
		$lang ??= $this->defaultLanguage;

		$ns = $this->namespaces[$namespace] ?? null;
		if(is_null($ns)){
			return sprintf("%s:%s", $namespace, $key);
		}

		$language = $ns->getLanguage($lang);
		if(is_null($language)){
			$langParts = explode("_", $lang);
			if(count($langParts) > 1){
				$language = $ns->getLanguage($langParts[0]);
			}
		}

		$langTranslation = $language?->getTranslation($key, $params);
		if($langTranslation !== null){
			return $langTranslation;
		}

		$defaultLangTranslation = $ns->getLanguage($this->defaultLanguage)?->getTranslation($key, $params);
		if($defaultLangTranslation !== null){
			return $defaultLangTranslation;
		}

		return sprintf("%s:%s", $namespace, $key);
	}
}

class TLNamespace{
	/**
	 * @var TLLanguage[]
	 */
	private array $languages = [];

	public function __construct(private string $name){
	}

	public function getName(): string{
		return $this->name;
	}

	public function load(string $path): void{
		// list json files
		$dirs = scandir($path);
		if($dirs === false){
			throw new \RuntimeException("Could not scan directory $path");
		}

		$files = array_filter($dirs, function(string $file) use ($path): bool{
			return pathinfo($path . "/" . $file, PATHINFO_EXTENSION) === "json";
		});
		foreach($files as $file){
			$language = new TLLanguage($file);
			$this->languages[str_replace(".json", "", $file)] = $language;
			$language->load($path . "/" . $file);
		}
	}

	public function getLanguage(string $name): ?TLLanguage{
		return $this->languages[$name] ?? null;
	}

	public function isEmpty(): bool{
		return empty($this->languages);
	}
}

class TLLanguage{
	/**
	 * @var array<string, string>
	 */
	private array $translations = [];

	public function __construct(private string $name){
	}

	public function getName(): string{
		return $this->name;
	}

	/**
	 * @throws \JsonException
	 * @throws \RuntimeException
	 */
	public function load(string $path): void{
		$file_contents = file_get_contents($path);
		if($file_contents === false){
			throw new \RuntimeException("Could not read file $path");
		}

		$translations = json_decode($file_contents, true, flags: JSON_THROW_ON_ERROR);
		if (!is_array($translations)){
			throw new \RuntimeException("Translation data must be an array");
		}

		foreach($translations as $key => $value){
			if (!is_string($key)){
				throw new \RuntimeException("Translation key must be a string");
			}
			if (!is_string($value)){
				throw new \RuntimeException("Translation value must be a string");
			}
		}

		$this->translations = $translations;
	}

	/**
	 * @param array<string, mixed> $params
	 */
	public function getTranslation(string $key, array $params = []): ?string{
		$str = $this->translations[$key] ?? null;
		if(is_null($str)){
			return null;
		}

		foreach($params as $key => $value){
			$str = str_replace("{{" . $key . "}}", strval($value), $str);
		}
		return $str;
	}
}