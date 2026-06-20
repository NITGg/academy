<?php


function send_message($var, $name, $time, $completeTime, $attemptid, $parentNum, $coursefullname)
{
    $message = 'اسم الطالب: ' . $name . "\n";
    $message .= 'درجة الاختبار: ' . $var . "\n";
    $message .= 'الوقت المستغرق في حل الاختبار: ' . $time . "\n";
    $message .= 'تم الانتهاء في: ' . $completeTime . "\n";
    $message .= 'المقرر الدراسي: ' . $coursefullname;

    // send mesaage with grade
    $paramsx = array(
        'token' => 'ef0v9xiirneue0ux',
        'to' => $parentNum, //201091568240
        'body' => $message
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.ultramsg.com/instance67657/messages/chat", // Replace with the correct URL
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => http_build_query($paramsx),
        CURLOPT_HTTPHEADER => array(
            "content-type: application/x-www-form-urlencoded"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return false;
        /* echo "cURL Error #:" . $err; */
    } else {
        return true;
        /* echo $response; */
    }
}

if (isset($_GET['parentnum'])) {
    $var = $_GET['message'];
    $name = $_GET['name'];
    $time = $_GET['time'];
    $completeTime = $_GET['completeTime'];
    $attemptid = $_GET['attemptid'];
    $parentNum = $_GET['parentnum'];
    $coursefullname = $_GET['coursename'];

    // You can call your send_message function here
    $success = send_message($var, $name, $time, $completeTime, $attemptid, $parentNum, $coursefullname);


    // if message sent
    if ($success) {
        require_once(__DIR__ . '/../../config.php');
        global $DB;
        if (!$DB->record_exists('quiz_whatsapp_messages', array('attempt_id' => $attemptid))) {
            // Define the data to insert
            $data = new stdClass();
            $data->attempt_id = $attemptid;
            $data->sent = $success;

            // Insert the record into the table
            $inserted = $DB->insert_record('quiz_whatsapp_messages', $data);

            if ($inserted) {
                echo "Record inserted successfully.";
            } else {
                echo "Error inserting the record.";
            }
        }
    }

    // You can also send a response back to the client if needed
    echo "Data received and processed.";
} else {
    echo "No data received.";
}
