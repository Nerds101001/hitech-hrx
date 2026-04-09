<?php

namespace Modules\AiChat\Services;

use App\Models\Settings;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use OpenAI;

class GptService
{
  protected $schemaService;

  public function __construct(SchemaService $schemaService)
  {
    $this->schemaService = $schemaService;
  }

  public function interpretQuery($userQuery)
  {
    // Retrieve the schema from the session
    $schema = Session::get('schema');

    if (!$schema) {
      return $this->initiateSessionWithSchema();
    }

    Log::info($schema);

    // Generate SQL query from user input
    $prompt = "You are an SQL database assistant. Based on the schema provided below, generate a valid SQL query:\n\n";
    $prompt .= "Schema:\n$schema\n\n";
    $prompt .= "User Query: \"$userQuery\"\n\n";
    $prompt .= "Requirements:\n";
    $prompt .= "- Ensure the query is syntactically correct.\n";
    $prompt .= "- Avoid SQL injection risks.\n";
    $prompt .= "- If you are unsure, return an error message instead of generating an invalid query.\n";
    $prompt .= "- If the query is related to attendance records, if the status of the attendance are checked_in or checked_out you can consider them as present.";
    $prompt .= "- If the query is related to employees/workers, you can consider them as users in the system.";
    $sqlQuery = $this->chatWithGpt($prompt);

    $cleanedResponse = preg_replace('/```sql|```/', '', $sqlQuery);

    $finalSqlQuery = strip_tags($cleanedResponse);

    $finalSqlQuery = trim($finalSqlQuery);

    if (!$this->validateSQL($finalSqlQuery)) {
      return "The generated SQL query appears to be invalid.";
    }

    // Execute the SQL query on the database
    try {
      $results = DB::select($finalSqlQuery);

      // Convert query result to natural language summary
      $response = $this->convertResultsToNaturalLanguageUsingGpt($results);

      return $response;
    } catch (Exception $e) {
      Log::error("SQL Error: " . $e->getMessage());
      return "Error executing the query: " . $e->getMessage();
    }
  }

  public function initiateSessionWithSchema()
  {
    $schema = $this->schemaService->getSchema();


    // Save schema in session (simulates "memory" for the model)
    Session::put('schema', $schema);

    // Prepare an initial system message
    $initialMessage = "Here is my database schema:\n$schema\nNow, I want to ask some questions about this schema.";

    return $this->chatWithGpt($initialMessage);
  }

  private function chatWithGpt($prompt)
  {
    $yourApiKey = Settings::first()->chat_gpt_key;
    $client = OpenAI::client($yourApiKey);

    $response = $client->chat()->create([
      'model' => 'gpt-4-turbo',
      'messages' => [
        ['role' => 'system', 'content' => 'You are a database assistant. You help users query a database schema.'],
        ['role' => 'user', 'content' => $prompt],
      ],
    ]);

    return $response['choices'][0]['message']['content'];
  }

  private function validateSQL($query)
  {
    try {
      DB::statement("EXPLAIN $query");
      return true;
    } catch (Exception $e) {
      Log::error("SQL Validation Error: " . $e->getMessage());
      return false;
    }
  }

  private function convertResultsToNaturalLanguageUsingGpt($results)
  {
    if (empty($results)) {
      return "No results found for your query.";
    }

    // Convert the query result to JSON for passing to GPT
    $jsonResults = json_encode(array_slice($results, 0, 5)); // Pass a few rows for summarization

    // Prepare the prompt for GPT to summarize the results in natural language
    $prompt = <<<PROMPT
Here is a JSON array of query results:
$jsonResults

Please summarize this data in clear, natural language. Ensure:
- Present the data as a coherent story.
- Avoid using JSON structure descriptions.
- Use simple, friendly language.
- If data is tabular, suggest key trends or patterns.
- Format the response as clean HTML with emojis where appropriate.
PROMPT;
    // Use the chatWithGpt method to get the natural language summary
    return $this->chatWithGpt($prompt);
  }

  public function interpretQueryV2($userQuery)
  {
    // Fetch a summarized schema
    $schemaSummary = $this->schemaService->getSchema(true);

    $extractTablePrompt = <<<EOD
You are an SQL assistant with knowledge of the following database schema:
$schemaSummary

Based on the user's query below, determine which table(s) are most relevant to answer the query. If multiple tables are involved, list them.

User Query: "$userQuery"

Only respond with the table names, separated by commas. Do not include any additional text.
EOD;

// Call OpenAI to extract table names
    $tablesResponse = $this->chatWithGpt($extractTablePrompt);
    Log::info('Tables response from GPT: ' . $tablesResponse);
    $tables = array_map('trim', explode(',', $tablesResponse));

    $schemaDetails = '';
    foreach ($tables as $tableName) {
      $schemaDetails .= $this->schemaService->getTableSchema($tableName) . "\n";
      Log::info('Table schema for ' . $tableName . ': ' . $schemaDetails);
    }

    $prompt = <<<EOD
You are an SQL assistant with the following table schema:

$schemaDetails

The user wants the following:

User Query: "$userQuery"

Points to consider:
- If the query is related to attendance records then if the status of the attendance are checked_in or checked_out you can consider them as present.

Generate a valid SQL query based on the schema. Do not include any explanation, just return the SQL query.
EOD;


    $sqlQuery = $this->chatWithGpt($prompt);

    $cleanedResponse = preg_replace('/```sql|```/', '', $sqlQuery);

    $finalSqlQuery = strip_tags($cleanedResponse);

    $finalSqlQuery = trim($finalSqlQuery);

    Log::info('Final SQL query: ' . $finalSqlQuery);

    if (!$this->validateSQL($finalSqlQuery)) {
      return "The generated SQL query appears to be invalid.";
    }

    // Execute the SQL query on the database
    try {
      $results = DB::select($finalSqlQuery);

      // Convert query result to natural language summary
      $response = $this->convertResultsToNaturalLanguageUsingGpt($results);

      return $response;
    } catch (Exception $e) {
      Log::error("SQL Error: " . $e->getMessage());
      return "Error executing the query: " . $e->getMessage();
    }
  }

  private function convertResultsToNaturalLanguageUsingGptV3($results)
  {
    if (empty($results)) {
      return "No relevant data found for your query.";
    }

    // Convert results to JSON (first few rows for clarity)
    $jsonResults = json_encode(array_slice($results, 0, 5)); // Take the top 5 rows

    // Prepare an optimized HRMS-specific GPT prompt
    $prompt = <<<PROMPT
You are an AI HR Assistant specializing in **Human Resource Management Systems (HRMS)** data analysis.

### Context:
The data you are summarizing comes from an HRMS system that includes:
- **Attendance Tracking** (e.g., Check-in/out times, late arrivals)
- **Leave Management** (e.g., Leave balance, leave trends)
- **Expense Reporting** (e.g., Claims, approvals, monthly summaries)
- **Employee Performance Analytics** (e.g., Sales targets, task completion)

### Objective:
Your task is to **analyze** the given data and generate a professional summary for HR and management purposes.

### Data (First few rows):
$jsonResults

### Instructions:
1. **Professional Tone:** Maintain a corporate and professional communication style.
2. **Insights:** Provide high-level insights and key takeaways relevant to HR management.
3. **Observations:** Highlight patterns, anomalies, or trends (e.g., recurring late arrivals, frequent leave patterns).
4. **Recommendations:** Offer professional advice or actions based on the observations.
5. **Clarity:** Avoid using technical jargon like *JSON* or *dataset*. Present in plain English.
7. **Formatting:** Use clean, readable **HTML** for presentation, use necessary embellishments or emojis.

### Example Response:
**Executive Summary:**
Overall attendance and performance trends indicate stable productivity across teams, with minor anomalies in leave patterns.

Now, analyze the provided data and produce a summary adhering to these instructions.
PROMPT;

    // Get a summarized response from GPT
    return $this->chatWithGpt($prompt);
  }

  private function convertResultsToNaturalLanguageUsingGptV2($results)
  {
    if (empty($results)) {
      return "No results found for your query.";
    }

    // Convert the query result to JSON for summarization
    $jsonResults = json_encode(array_slice($results, 0, 5)); // Take the first 5 rows for clarity

    // Build the improved prompt for GPT
    $prompt = <<<PROMPT
You are an AI data assistant tasked with summarizing SQL query results into a natural, human-readable format.

### Data:
$jsonResults

### Guidelines for Summarization:
1. Present the summary as a **very short story** or **insightful analysis**, not as raw data.
2. Avoid mentioning words like *"query"*, *"dataset"*, or *"JSON"*.
3. Highlight **key insights**, **trends**, and **interesting observations** from the data.
4. If possible, use **bullet points** or **numbered lists** for clarity.
5. If the data suggests any **recommendations** or **actions**, mention them.
6. Keep the response **friendly**, **clear**, and **concise**.
7. Format the response in **clean HTML**. Include **emojis** where appropriate to make it engaging.
8. Avoid repeating column names excessively; focus on the **key takeaways**.
9. This is a HRMS application and the data is related to employees, attendance and other HR related information.

### Example Response:
- ✅ *The total revenue increased by 15% last month.*
- 📊 *Customer satisfaction scores are trending upwards.*
- 📅 *The most active period was between 9:00 AM and 11:00 AM.*

### Now, please summarize the data accordingly.
PROMPT;

    // Get the natural language summary using GPT
    return $this->chatWithGpt($prompt);
  }

  private function convertResultsToNaturalLanguage($results)
  {
    if (empty($results)) {
      return "No results found for your query.";
    }

    // Example: summarize the first few results
    $summary = "The query returned " . count($results) . " records. Here are some details:\n";

    foreach (array_slice($results, 0, 3) as $result) {
      $summary .= json_encode($result) . "\n"; // You can format this in a more readable way
    }

    return $summary;
  }


  private function extractTableNameFromKeywords($userQuery)
  {
    $keywords = [
      'orders' => 'orders',
      'clients' => 'clients',
      'employees' => 'employees',
      'attendance' => 'attendances'
    ];

    foreach ($keywords as $keyword => $table) {
      if (stripos($userQuery, $keyword) !== false) {
        return $table;
      }
    }

    return null;
  }
}
