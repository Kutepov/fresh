<?php namespace console\controllers;

use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;
use Yii;
use yii\console\Exception;
use yii\db\Connection;
use yii\di\Instance;

class MessageController extends \yii\console\controllers\MessageController
{
    /**
     * Extracts messages to be translated from source code.
     *
     * This command will search through source code files and extract
     * messages that need to be translated in different languages.
     *
     * @param string $configFile the path or alias of the configuration file.
     * You may use the "yii message/config" command to generate
     * this file and then customize it for your needs.
     * @throws Exception on failure.
     */
    public function actionExtract($configFile = null)
    {
        $configFileContent = [];
        if ($configFile !== null) {
            $configFile = Yii::getAlias($configFile);
            if (!is_file($configFile)) {
                throw new Exception("The configuration file does not exist: $configFile");
            }
            $configFileContent = require($configFile);
        }

        $config = array_merge(
            $this->getOptionValues($this->action->id),
            $configFileContent,
            $this->getPassedOptionValues()
        );


        $result = [];

        foreach ((array)$config['sourcePath'] as $sourcePath) {
            $config['sourcePath'] = Yii::getAlias($sourcePath);
            $sourcePath = $config['sourcePath'];
            $config['messagePath'] = Yii::getAlias($config['messagePath']);

            if (!isset($sourcePath, $config['languages'])) {
                throw new Exception('The configuration file must specify "sourcePath" and "languages".');
            }
            if (!is_dir(Yii::getAlias($sourcePath))) {
                throw new Exception("The source path {$sourcePath} is not a valid directory.");
            }
            if (empty($config['format']) || !in_array($config['format'], ['php', 'po', 'pot', 'db'])) {
                throw new Exception('Format should be either "php", "po", "pot" or "db".');
            }
            if (in_array($config['format'], ['php', 'po', 'pot'])) {
                if (!isset($config['messagePath'])) {
                    throw new Exception('The configuration file must specify "messagePath".');
                }
                if (!is_dir($config['messagePath'])) {
                    throw new Exception("The message path {$config['messagePath']} is not a valid directory.");
                }
            }
            if (empty($config['languages'])) {
                throw new Exception('Languages cannot be empty.');
            }


            $files = FileHelper::findFiles(realpath($sourcePath), $config);

            $messages = [];
            foreach ($files as $file) {
                $messages = array_merge_recursive($messages, $this->extractMessages($file, $config['translator'], $config['ignoreCategories']));
            }

            $result = array_merge($result, (array)$messages['array'], (array)$messages['app']);
        }

        $messages = ['app' => $result];

        $catalog = isset($config['catalog']) ? $config['catalog'] : 'messages';

        if (in_array($config['format'], ['php', 'po'])) {
            foreach ($config['languages'] as $language) {
                $dir = $config['messagePath'] . DIRECTORY_SEPARATOR . $language;
                if (!is_dir($dir) && !@mkdir($dir)) {
                    throw new Exception("Directory '{$dir}' can not be created.");
                }
                if ($config['format'] === 'po') {
                    $this->saveMessagesToPO($messages, $dir, $config['overwrite'], $config['removeUnused'], $config['sort'], $catalog, $config['markUnused']);
                } else {
                    $this->saveMessagesToPHP($messages, $dir, $config['overwrite'], $config['removeUnused'], $config['sort'], $config['markUnused']);
                }
            }

            $this->stdout("\n\nNOW RUN ./yii i18n/db-import\n\n");
        } elseif ($config['format'] === 'db') {
            /** @var Connection $db */
            $db = Instance::ensure($config['db'], Connection::className());
            $sourceMessageTable = isset($config['sourceMessageTable']) ? $config['sourceMessageTable'] : '{{%source_message}}';
            $messageTable = isset($config['messageTable']) ? $config['messageTable'] : '{{%message}}';
            $this->saveMessagesToDb(
                $messages,
                $db,
                $sourceMessageTable,
                $messageTable,
                $config['removeUnused'],
                $config['languages'],
                $config['markUnused']
            );
        } elseif ($config['format'] === 'pot') {
            $this->saveMessagesToPOT($messages, $config['messagePath'], $catalog);
        }
    }

    protected function extractMessages($fileName, $translator, $ignoreCategories = [])
    {
        $coloredFileName = Console::ansiFormat($fileName, [Console::FG_CYAN]);
        $this->stdout("Extracting messages from $coloredFileName...\n");

        $subject = file_get_contents($fileName);
        $messages = [];
        $tokens = token_get_all($subject);
        foreach ((array)$translator as $currentTranslator) {
            $translatorTokens = token_get_all('<?php ' . $currentTranslator);
            array_shift($translatorTokens);
            $messages = array_merge_recursive($messages, $this->extractMessagesFromTokens($tokens, $translatorTokens, $ignoreCategories));
        }


        return $messages;
    }

    protected function extractMessagesFromTokens(array $tokens, array $translatorTokens, array $ignoreCategories)
    {
        $messages = [];
        $translatorTokensCount = count($translatorTokens);
        $matchedTokensCount = 0;
        $buffer = [];
        $pendingParenthesisCount = 0;

        foreach ($tokens as $token) {
            // finding out translator call
            if ($matchedTokensCount < $translatorTokensCount) {
                if ($this->tokensEqual($token, $translatorTokens[$matchedTokensCount])) {
                    $matchedTokensCount++;
                } else {
                    $matchedTokensCount = 0;
                }
            } elseif ($matchedTokensCount === $translatorTokensCount) {
                // translator found

                // end of function call
                if ($this->tokensEqual(')', $token)) {
                    $pendingParenthesisCount--;

                    if ($pendingParenthesisCount === 0) {
                        if ($buffer[0][0] === T_CONSTANT_ENCAPSED_STRING) {

                            $message = ($buffer[0][1]);
                            $message = mb_substr($message, 1, -1);
                            $messages['app'][] = $message;
                            $nestedTokens = array_slice($buffer, 3);
                            if (count($nestedTokens) > $translatorTokensCount) {
                                // search for possible nested translator calls
                                $messages = array_merge_recursive($messages, $this->extractMessagesFromTokens($nestedTokens, $translatorTokens, $ignoreCategories));
                            }
                        } else {
                            print_r($buffer);
                            // invalid call or dynamic call we can't extract
                            $line = Console::ansiFormat($this->getLine($buffer), [Console::FG_CYAN]);
                            $skipping = Console::ansiFormat('Skipping line', [Console::FG_YELLOW]);
                            $this->stdout("$skipping $line. Make sure both category and message are static strings.\n");
                        }

                        // prepare for the next match
                        $matchedTokensCount = 0;
                        $pendingParenthesisCount = 0;
                        $buffer = [];
                    } else {
                        $buffer[] = $token;
                    }
                } elseif ($this->tokensEqual('(', $token)) {
                    // count beginning of function call, skipping translator beginning
                    if ($pendingParenthesisCount > 0) {
                        $buffer[] = $token;
                    }
                    $pendingParenthesisCount++;
                } elseif (isset($token[0]) && !in_array($token[0], [T_WHITESPACE, T_COMMENT])) {
                    // ignore comments and whitespaces
                    $buffer[] = $token;
                }
            }
        }

        return $messages;
    }

    protected function saveMessagesToPHP($messages, $dirName, $overwrite, $removeUnused, $sort, $markUnused)
    {
        foreach ($messages as $category => $msgs) {
            $file = str_replace("\\", '/', "$dirName/$category.php");
            $path = dirname($file);
            FileHelper::createDirectory($path);
            $msgs = array_values(array_unique($msgs));
            $coloredFileName = Console::ansiFormat($file, [Console::FG_CYAN]);
            $this->stdout("Saving messages to $coloredFileName...\n");
            $this->saveMessagesCategoryToPHP($msgs, $file, $overwrite, $removeUnused, $sort, $category, $markUnused);
        }
    }

    /**
     * Writes category messages into PHP file
     *
     * @param array $messages
     * @param string $fileName name of the file to write to
     * @param bool $overwrite if existing file should be overwritten without backup
     * @param bool $removeUnused if obsolete translations should be removed
     * @param bool $sort if translations should be sorted
     * @param string $category message category
     * @param bool $markUnused if obsolete translations should be marked
     * @return int exit code
     */
    protected function saveMessagesCategoryToPHP($messages, $fileName, $overwrite, $removeUnused, $sort, $category, $markUnused)
    {
        if (is_file($fileName)) {
            $unused = [];
            $rawExistingMessages = require($fileName);
            $ruMessages = require Yii::getAlias('@frontend/messages/ru/app.php');

            $existingMessages = $rawExistingMessages;
             sort($messages);
             ksort($existingMessages);
            if (array_keys($existingMessages) === $messages && (!$sort || array_keys($rawExistingMessages) === $messages)) {
                $this->stdout("Nothing new in \"$category\" category... Nothing to save.\n\n", Console::FG_GREEN);
                return self::EXIT_CODE_NORMAL;
            }
            unset($rawExistingMessages);
            $merged = [];
            $untranslated = [];
            foreach ($messages as $message) {
                if (array_key_exists($message, $existingMessages) && $existingMessages[$message] !== '') {
                    $merged[$message] = $existingMessages[$message];
                } else {
                    $untranslated[] = $message;
                }
            }
            $todo = [];
            foreach ($untranslated as $message) {
                $todo[$message] = '';
            }
            foreach ($existingMessages as $message => $translation) {
                if (!is_array($translation) && !$removeUnused && !isset($merged[$message]) && !isset($todo[$message])) {
                    if (!empty($translation) && (!$markUnused || (strncmp($translation, '@@', 2) === 0 && substr_compare($translation, '@@', -2, 2) === 0))) {
                        $todo[$message] = $translation;
                    } else {
                        $unused[] = $message;
                        $todo[$message] = '@@' . $translation . '@@';
                    }
                } elseif (is_array($translation)) {
                    $todo[$message] = $translation;
                }

            }
            $merged = array_merge($todo, $merged);
            if (false === $overwrite) {
                $fileName .= '.merged';
            }
            $this->stdout("Translation merged.\n");
        } else {
            $merged = [];
            foreach ($messages as $message) {
                $merged[$message] = '';
            }
        }

//        $merged = array_replace(array_flip(array_keys($ruMessages)), $merged); //TODO: comment
        if (count($unused)) {
            foreach ($merged as $k => $v) {
                if (in_array($k, $unused)) {
                    unset($merged[$k]);
                }
            }
        }

//TODO: export
        foreach ($merged as $k => $v) {
            if (!$merged[$k]) {
              //  $merged[$k] = $k;
            }
        }

        $array = VarDumper::export($merged);
        $array = preg_replace('#,(\n[\s]+\])#si', "$1", $array);
        $array = str_replace('\\\\', "\\", $array);

        $content = <<<EOD
<?php
return $array;

EOD;

        if (file_put_contents($fileName, $content) === false) {
            $this->stdout("Translation was NOT saved.\n\n", Console::FG_RED);
            return self::EXIT_CODE_ERROR;
        }

        $this->stdout("Translation saved.\n\n", Console::FG_GREEN);
        return self::EXIT_CODE_NORMAL;
    }
}