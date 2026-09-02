<?php 
namespace App\Service\Forms;
use Psr\Log\LoggerInterface;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\LegacyDB;

class FormFunctions
{
    public LegacyDB $db;
    public function __construct(
        LegacyDB $db_instance,
    ) {
        $this->db = $db_instance;
    }


    /*
        Returns the name of a page corresponding to a page number.
        The pages are numbered by their order in the form's index.json file
        Pages are 1-indexed
        If the page number is invalid, it defaults to the page name at page 1.
        Args:
            $formName (String): The name of the form.
            $pageNumber (int): The needed page number.
        Returns:
            (String): The name of the page.
    */
    function getPageNameFromNumber($formName, $pageNumber){

        $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
        if (!file_exists($path)) {
            throw new NotFoundHttpException("Form not found.");
        }
        
        $form = json_decode(file_get_contents($path), true);

        if ($pageNumber < 1 || $pageNumber > $this->getPageCount($formName)) {
            $pageNumber = 1;
        }

        return ($form['pages'])[$pageNumber - 1]['fileName'];
    }


    /*
        Checks if all the pages for a form are completed.
        In the index.json file, each page has a sql table specified.
        If the user has an entry in that table, the page is considered done.
        Args:
            $formName (String): The name of the form.
        Returns:
            (Boolean): True if the form is done.
    */
    function allPagesDone($formName) {
        $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
        if (!file_exists($path)) {
            throw new NotFoundHttpException("Form not found.");
        }
        
        $form = json_decode(file_get_contents($path), true);
        $pdo = $this->db->db();
        foreach ($form['pages'] as $page) {
            try {
                $query = "SELECT EXISTS(SELECT 1 FROM " . $page['tableName'] . " WHERE user_id = :user_id)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $count = $stmt->fetchColumn();

                if ($count == 0) {
                    return false;
                }
            } catch (\PDOException $e) {
                return false;
            }
        }
        return true;
    }


    /*
        Returns the names of pages for a form
        Looks in the index.json file of the specified form to retreive the page names
        Available form names are the names of the sub-folders under the "forms" folder.
        Args:
            $formName (String): The name of the form.
        Returns:
            (String Array): A list of all the page names for the form.
    */
    function getAllPageNames($formName) {
        $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
        if (!file_exists($path)) {
            throw new NotFoundHttpException("Form not found.");
        }
        
        $form = json_decode(file_get_contents($path), true);

        $pageNames = [];
        foreach ($form['pages'] as $page) {
            array_push($pageNames, $page['fileName']);
        }
        return $pageNames;
    }


    /*
        Loads the data from the json file of a form page
        Args:
            $formName (String): The name of the form.
            $pageNumber (Int): The name of the page.
        Returns:
            (Object): A PHP object of the data.
    */
    function loadFormPage($formName, $pageName) {
        $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . $pageName . ".json";

        if (!file_exists($path)) {
            throw new NotFoundHttpException("Form not found. Looking for [".$formName."/".$pageName."]");
        }

        return json_decode(file_get_contents($path), true);
    }


    /*
        Gets the count of pages for a specified form.
        The number of pages is equal to the number of objects listed in the 'pages' array of the form's index.json file.
        Args:
            $formName (String): The name of the form.
        Returns:
            (Int): The number of pages.
    */
    function getPageCount($formName) {
        $path = getenv('ABET_PRIVATE_DIR') . "/" . "forms" . "/" . $formName . "/" . "index.json";
        if (!file_exists($path)) {
            throw new NotFoundHttpException("Form not found.");
        }
        $form = json_decode(file_get_contents($path), true);
        
        return count($form['pages']);
    }


    # in form_functions
    function formatReviewValue($v): string {
        if ($v === null) return "";
        if (is_bool($v)) return $v ? "Yes" : "No";
        if (is_numeric($v)) return (string)$v;
        if (is_string($v)) return $v;
        return "";
    }

    public function decodeGridRows($v): array {
        if (is_array($v)) return $v;
        if (is_string($v) && trim($v) !== "") {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) return $decoded;
        }
        return [];
    }

}