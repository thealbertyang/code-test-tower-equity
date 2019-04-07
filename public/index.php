<?php

function get_json(){
  $base = "https://api.github.com/search/repositories?q=language:go&sort=stars";
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $base);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
  $content = curl_exec($curl);
  curl_close($curl);
  return $content;
}

function transform_data($json) {
	$data = $json['items'];
	$newData = [];
	foreach($data as $key => $val){
		//echo json_encode($data[$key]);
		$newData[$key]['login'] = $data[$key]['owner']['login'];
		$newData[$key]['name'] = $data[$key]['name'];
		$newData[$key]['html_url'] = $data[$key]['html_url'];
		$newData[$key]['stargazers_count'] = $data[$key]['stargazers_count'];
		$newData[$key]['forks'] = $data[$key]['forks'];
		$newData[$key]['open_issues'] = $data[$key]['open_issues'];
		$newData[$key]['created_at'] = $data[$key]['created_at'];
		$newData[$key]['pushed_at'] = $data[$key]['pushed_at'];
	}
	//echo json_encode($newData);
	return $newData;
}

function tableExists($pdo, $table) {
    // Try a select statement against the table
    // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
    } catch (Exception $e) {
        // We got an exception == table not found
        return FALSE;
    }
    // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
    return $result !== FALSE;
}

function truncateTable($pdo){
  try {
      $pdo->beginTransaction();
      $stmt = $pdo->prepare("DELETE FROM github");
      $stmt->execute();
  } catch(PDOException $ex) {
      //Something went wrong rollback!
      echo $ex->getMessage();
  }
  $pdo->commit();
}

function createTable($pdo){
  $table = "github";
  try {
       $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );//Error Handling
       $sql ="CREATE table $table(
       id INT( 11 ) AUTO_INCREMENT PRIMARY KEY,
       login VARCHAR( 50 ) NOT NULL,
       name VARCHAR( 250 ) NOT NULL,
       html_url VARCHAR( 150 ) NOT NULL,
       stargazers_count VARCHAR( 150 ) NOT NULL,
       forks VARCHAR( 150 ) NOT NULL,
       open_issues VARCHAR( 100 ) NOT NULL,
       created_at VARCHAR( 50 ) NOT NULL,
       pushed_at VARCHAR( 50 ) NOT NULL);" ;
       $pdo->exec($sql);
       print("Created $table Table.\n");

  } catch(PDOException $e) {
      echo $e->getMessage();//Remove or change message in production code
  }
  $pdo->commit();
}

function placeholders($text, $count=0, $separator=","){
    $result = array();
    if($count > 0){
        for($x=0; $x<$count; $x++){
            $result[] = $text;
        }
    }
    return implode($separator, $result);
}

function insertGithub($pdo, $json){
  $datafields = array('login', 'name', 'html_url', 'stargazers_count', 'forks', 'open_issues', 'created_at', 'pushed_at');
  $data = transform_data($json);
  $pdo->beginTransaction(); // also helps speed up your inserts.
  $insert_values = array();
  foreach($data as $d){
      $question_marks[] = '('  . placeholders('?', sizeof($d)) . ')';
      $insert_values = array_merge($insert_values, array_values($d));
  }
  $sql = "INSERT INTO github (" . implode(",", $datafields ) . ") VALUES " .
         implode(',', $question_marks);
  $stmt = $pdo->prepare($sql);
  try {
      if($stmt->execute($insert_values)){
      }
      else {
        $errorcode = $stmt->errorCode();
      }
      //echo json_encode($insert_values);
  } catch (PDOException $e){
      echo $e->getMessage();
  }
  $pdo->commit();
}

function fetchAllGithub($pdo){
  $pdo->beginTransaction(); // also helps speed up your inserts.
  $result = $pdo->prepare("SELECT login, name, html_url, stargazers_count, forks, open_issues, created_at, pushed_at FROM github");
  $result->execute();

  $newData = [];

  while ($row = $result->fetchAll(PDO::FETCH_ASSOC))
  {
    //$title = $row['login'];
    //$body = $row['name'];
    echo json_encode($row);
    $newData[] = $row;
  }
  $pdo->commit();

  return $newData;
}

try {
    $dsn = 'mysql:host=mysql;dbname=code-test;charset=utf8;port=3306';
    $pdo = new PDO($dsn, 'root', 'codeTest123');
    $json = json_decode(get_json(), true);

    if(tableExists($pdo, 'github')){
      //clear table
      truncateTable($pdo);
    }
    else {
      createTable($pdo);
      echo 'it doesnt exist';
    }
    //add recent data to table
    insertGithub($pdo, $json);

?>
<html>
  <head>
    <script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>
    <style>
      html, body {
        font-family: Arial, sans-serif;
        padding: 0;
        margin: 1rem 0;
        display: flex;
        justify-content: center;
        align-items: start;
      }

      h3 {
          margin: 0 0 1rem 0;
      }

      #app {
        display: flex;
        -ms-flex-wrap: wrap;
        flex-wrap: wrap;
        margin-right: -15px;
        margin-left: -15px;
        width: 1460px;
      }

      .col-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
        position: relative;
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        box-sizing: border-box;
      }

      .col-12 {
        flex: 0 0 100%;
        max-width: 100%;
        position: relative;
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        box-sizing: border-box;
      }

      .card {
         box-shadow: rgba(0, 0, 0, 0.117647) 0px 1px 6px, rgba(0, 0, 0, 0.117647) 0px 1px 4px;
         transition: .15s all ease-in-out;
         border-radius: .25rem;
      }

      .cardBody {
        padding: 1rem 1rem;
      }

      .cardBody b {
        color: #696969;
      }

      .mb-3 {
        margin-bottom: 1rem!important;
      }

    </style>
  </head>
  <body>
    <div id="app">
        <div class='col-12 mb-3'>
           <input type="text" v-model="search" placeholder="Search title.."/>
        </div>
        <div class='col-4 mb-3' v-for="item in filteredList">
          <div class='card'>
            <div class='cardbody'>
              <h3>{{ item.login }}</h3>
              <b>repo:</b> {{ item.name }}<br>
              <b>url:</b> {{ item.html_url }}<br>
              <b>stars:</b> {{ item.stargazers_count }}<br>
              <b>forks:</b> {{ item.forks }}<br>
              <b>open issues:</b> {{ item.open_issues }}<br>
              <b>created:</b> {{ item.created_at }}<br>
              <b>last pushed:</b> {{ item.pushed_at }}<br>
            </div>
          </div>
        </div>
    </div>
    <script>
      var app = new Vue({
        el: '#app',
        data: {
          search: '',
          github: <?php json_encode(fetchAllGithub($pdo)) ?>
        },
        computed: {
          filteredList() {
            return this.github.filter(item => {
              return item.login.toLowerCase().includes(this.search.toLowerCase())
            })
          }
        }
      })
    </script>
  </body>
</html>

<?php
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
