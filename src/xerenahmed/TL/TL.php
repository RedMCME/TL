<?php
// @time 10.08.2022 08:54
// @author eren
// "so much depends upon a red wheel barrow..."
declare(strict_types=1);

namespace xerenahmed\TL;

class TL{
	/**
	 * @var TLNamespace[]
	 */
	private array $namespaces = [];

	private string $defaultNamespace = "default";
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

	public function load(string $path): void{
		// list directories
		$directories = array_filter(scandir($path), function (string $file) use ($path): bool{
			return is_dir($path . "/" . $file) and $file !== "." and $file !== "..";
		});
		foreach($directories as $directory){
			$namespace = new TLNamespace($directory);
			$this->namespaces[$directory] = $namespace;
			$namespace->load($path . "/" . $directory);
		}
		if(!isset($this->namespaces[$this->defaultNamespace])){
			throw new \RuntimeException("Default namespace not found");
		}
		if(is_null($this->namespaces[$this->defaultNamespace]->getLanguage($this->defaultLanguage))){
			throw new \RuntimeException("Default language not found");
		}
	}

	public function useTranslation(?string $lang = null, ?string $namespace = null): \Closure{
		$namespace ??= $this->defaultNamespace;
		$lang ??= $this->defaultLanguage;

		if(!isset($this->namespaces[$namespace])){
			throw new \InvalidArgumentException("Namespace $namespace does not exist");
		}
		return function (string $key, array $params = []) use ($namespace, $lang): string{
			return $this->translate($key, $params, $lang, $namespace);
		};
	}

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
		if(is_null($language)){
			$language = $ns->getLanguage($this->defaultLanguage);
		}

		$langTranslation = $language->getTranslation($key, $params);
		if ($langTranslation !== null){
			return $langTranslation;
		}

		$defaultLangTranslation = $ns->getLanguage($this->defaultLanguage)->getTranslation($key, $params);
		if ($defaultLangTranslation !== null){
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
		$files = array_filter(scandir($path), function (string $file) use ($path): bool{
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
}

class TLLanguage{
	/**
	 * @var Array<string, string>
	 */
	private array $translations = [];

	public function __construct(private string $name){
	}

	public function getName(): string{
		return $this->name;
	}

	public function load(string $path): void{
		$this->translations = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
	}

	public function getTranslation(string $key, array $params = []): ?string{
		$str = $this->translations[$key] ?? null;
		if (is_null($str)) {
			return null;
		}

		foreach($params as $key => $value){
			$str = str_replace("{{" . $key . "}}", $value, $str);
		}
		return $str;
	}
}