<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\Commands;
use Exception;
use Psr\Log\LoggerInterface;

class MakeModel extends BaseCommand
{
    protected $group = "Generators";
    protected $name = "make:model";
    protected $description = "Generates a model file.";

    /**
     * CodeIgniter Database BaseConnection
     *
     * @var \CodeIgniter\Database\BaseConnection
     */
    protected $db = null;

    /**
     * MakeModel Config
     *
     * @var \Config\MakeModel
     */
    protected $config = null;
    protected $options = [];

    /**
     * Model path
     */
    const MODEL_PATH = APPPATH . "Models" . DIRECTORY_SEPARATOR;

    public function __construct(LoggerInterface $logger, Commands $commands)
    {
        parent::__construct($logger, $commands);
        $this->config = new \Config\MakeModel();
        $this->db = db_connect();
    }

    public function run(array $params)
    {
        try {
            if (!is_writable(self::MODEL_PATH)) {
                throw new Exception("Model path is not writable.");
            }
            $this->options["table"] = trim(CLI::prompt("Input your table name"));
            if (!$this->db->tableExists($this->options["table"])) {
                throw new Exception("Table not found.");
            }
            $this->_makeDefaultOptions();
            $this->_saveModel();
            CLI::print("done\n");
        } catch (Exception $e) {
            $this->showError($e);
        }
    }

    private function _makeDefaultOptions()
    {
        helper("inflector");
        $this->options["model_name"] = ucfirst(camelize(singular($this->options["table"]))) . "Model";
        $this->options["model_file"] = self::MODEL_PATH . $this->options["model_name"] . ".php";
        $this->options["protected"] = [
            "table"          => $this->options["table"],
            "primaryKey"     => $this->config->primaryKey,
            "useTimestamps"  => $this->config->useTimestamps,
            "useSoftDeletes" => $this->config->useSoftDeletes,
        ];
        $allowedFileds = $this->db->getFieldNames($this->options["table"]);
        $this->options["protected"]["allowedFields"] = array_filter($allowedFileds, function ($item) {
            return !in_array($item, $this->_filterAllowedFields());
        });
    }

    private function _filterAllowedFields()
    {
        return [
            $this->config->primaryKey,
            $this->config->createdField,
            $this->config->updatedField,
            $this->config->deletedField,
        ];
    }

    private function _saveModel()
    {
        $content = <<<EOF
<?php
namespace App\Models;

use CodeIgniter\Model;

class {$this->options["model_name"]} extends Model {

EOF;
        foreach ($this->options["protected"] as $k => $v) {
            $content .= '    protected $' . $k . " = ";
            if (is_string($v)) {
                $content .= '"' . addslashes($v) . '"';
            } elseif (is_bool($v)) {
                $content .= $v ? "true" : "false";
            } elseif (is_array($v)) {
                $content .= "[" . implode(", ", array_map(function ($item) {
                    return '"' . addslashes($item) . '"';
                }, $v)) . "]";
            } else {
                $content .= $v;
            }
            $content .= ";\n";
        }
        $content .= "}\n";
        file_put_contents($this->options["model_file"], $content);
    }
}
