<?php

require_once('auth.php');

//upload errors
$error_types=array("No error","The uploaded file exceeds the permitted
filesize, which is 20 KB.","The uploaded file was only partially
uploaded.","No file was uploaded","Missing a temporary folder","Failed
to write file to disk.","A PHP extension stopped the file upload.");

//upload files only if they are less than 20KB
if ($_FILES["file"]["size"] < 20000) 
{

	if ($_FILES["file"]["error"] == 0)
	{

		//path to uploaded files
		$upload_path = '../tmp/';

		//extracting the extension
		$file_parts = pathinfo($_FILES["file"]["name"]);

		//upload only zip, ss and rkt files
		if ($file_parts['extension'] == "zip" | "ss" | "rkt")
		{

			move_uploaded_file($_FILES["file"]["tmp_name"], $upload_path . $_FILES["file"]["name"]);
			echo "Successfully uploaded : \"" . $_FILES["file"]["name"] . "\"", "\n";
			$upload_file_name = $_FILES["file"]["name"];

			//connection and authentication to the test server
			$conn1 = ssh2_connect('10.2.48.13', 22);
			ssh2_auth_password($conn1, 'evaluator', '123456');

			//create file storage directory for each user
			$cmd1 = 'mkdir upload-files/' . $_SESSION['username'] .'; mkdir test-results/' . $_SESSION['username'] . ';';
			ssh2_exec($conn1, $cmd1);

			//path to uploaded files on server
			$server_upload_path = 'upload-files/' . $_SESSION['username'];
  		
	 		//send the uploaded file to the directory created
			ssh2_scp_send($conn1, $upload_path . $upload_file_name, $server_upload_path . "/" . $upload_file_name, 0644);

			//send the uploaded file to the user's directory
			ssh2_exec($conn1, 'sudo -u ' . $_SESSION['username'] . ' /usr/bin/evaluate ' . $server_upload_path . "/" . $upload_file_name . ";");

			//closing the connection
			ssh2_exec($conn1, 'exit;');

			//connection and authentication using the user credentials to the test server
			$conn2 = ssh2_connect('10.2.48.13', 22);
			ssh2_auth_password($conn2, $_SESSION['username'], $_SESSION['password']);

			//extracting the file name without any extension
			$file_name = $file_parts['filename'];
			$file = basename($file_name, ".ss");

			//processing on zip file
			if ($file_parts['extension'] = 'zip')
			{

				$cmd2 = "unzip -o uploaded-files/" . $file. " -d uploaded-files/" . $file . "/ 2>&1 > uploaded-files/zip.output; cp -r /home/evaluator/test-cases/" . $file . "/* uploaded-files/" . $file . "/; racket uploaded-files/" . $file . "/" . $file . "-test.rkt >& uploaded-files/" . $file . "-result; rm -rf uploaded-files/" . $file . "/; rm uploaded-files/zip.output;";
				ssh2_exec($conn2, $cmd2);

			}

			//closing the connection
			ssh2_exec($conn2, 'exit;');

			$cmd3 = 'cp /home/' . $_SESSION['username'] . '/uploaded-files/' . $file . '-result test-results/' . $_SESSION['username'] . '/;';
			ssh2_exec($conn1, $cmd3);

			//closing the connection
			ssh2_exec($conn1, 'exit;');

			//deleting files in the repository
			exec('rm *' . $upload_path);

		}

		else
		        echo "File Type not recognized.";
		  
	}
	else
	{
                $error_message = $error_types[$_FILES["file"]["error"]];
		echo "Error Message: " . $error_message . "<br />";
	}
}
else
{
	echo "File size exceeds the permitted limit of 20 KB.";
}	
echo "<br /><a href='member-index.php'>Upload and Test more...</a><br /><br />";