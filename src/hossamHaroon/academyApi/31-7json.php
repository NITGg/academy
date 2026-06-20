<?php

// $json=$DB->get_records_sql("SELECT * from mdl_user ");
// require_once('../../config.php');
require_once(__DIR__ . '/../../config.php');

$PAGE->set_url($CFG->wwwroot . '/json/json.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/enrol/externallib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/group/externallib.php');
require_once($CFG->dirroot . '/lib/grouplib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/message/externallib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once($CFG->dirroot . '/mod/resource/locallib.php');
require_once($CFG->libdir . "/weblib.php");
// require '../vimeo/vendor/autoload.php';

// use Vimeo\Vimeo;
// require_once('lib.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('PHPMailer/src/Exception.php');
require_once('PHPMailer/src/PHPMailer.php');
require_once('PHPMailer/src/SMTP.php');

define('PARAM_STRING', 'string');
header('Content-Type: application/json');

// echo PARAM_STRING;
$function = optional_param('function', '', PARAM_RAW);
$id = optional_param('id', -1, PARAM_INT);
$token = optional_param('token',  0, PARAM_TEXT);
$username = optional_param('username',  0, PARAM_TEXT);
$year  = optional_param('year',  0, PARAM_INT);
$teacherRating  = optional_param('rating',  0, PARAM_INT);
$feedBackText = optional_param('feedback', 0, PARAM_TEXT);
$courseId = optional_param('courseID', -1, PARAM_INT);
$categoryId = optional_param('categoryId', -1, PARAM_INT);
$badgeId = optional_param('badgeId', -1, PARAM_INT);
$teacherId = optional_param('teacherId', -1, PARAM_INT);
$userids = optional_param('userids', array(), PARAM_INT);
$members = optional_param('members', array(), PARAM_INT);
$courseIDs = optional_param('courseIDs', array(), PARAM_INT);
$feedBackId = optional_param('feedbackId', -1, PARAM_INT);
$userFirstName = optional_param('firstname', 0, PARAM_TEXT);
$userlastName = optional_param('lastname', 0, PARAM_TEXT);
$imageId = optional_param('imageID', -1, PARAM_INT);
$videoID = optional_param('videoID', -1, PARAM_INT);
$groupID = optional_param('groupID', -1, PARAM_INT);
$email = optional_param('email',  0, PARAM_TEXT);
$requestedid = optional_param('requestedid', -1, PARAM_INT);
$phone1 = optional_param('phone1', 0, PARAM_TEXT);
$phone2 = optional_param('phone2', 0, PARAM_TEXT);
$secret = optional_param('secret', 0, PARAM_TEXT);
$code = optional_param('code', 0, PARAM_TEXT);
$name = optional_param('name', 0, PARAM_TEXT);
$courseModuleId = optional_param('cm', -1, PARAM_INT);
$rate = optional_param('rate', -1, PARAM_INT);
$fname = optional_param('fname', 0, PARAM_TEXT);
$lname = optional_param('lname', 0, PARAM_TEXT);
$role = optional_param('role', 0, PARAM_TEXT);
$password = optional_param('password', 0, PARAM_TEXT);
$city = optional_param('city', '', PARAM_RAW);
$school = optional_param('school', '', PARAM_RAW);
$knownfrom = optional_param('knownfrom', '', PARAM_RAW);
$center = optional_param('center', '', PARAM_RAW);
$language = optional_param('language', '', PARAM_RAW);
$deviceid = optional_param('deviceid', '', PARAM_RAW);
$device = optional_param('device', '', PARAM_RAW);
$activityid = optional_param('activityid', '', PARAM_RAW);
$code = optional_param('code', '', PARAM_RAW);
$quiz = optional_param('quiz', 0, PARAM_TEXT);
$option = optional_param('option', ' ', PARAM_RAW);

if ($function == 'termsInfo') {
    echo termsInfo();
}
if ($function == 'aboutInfo') {
    echo aboutInfo();
}
// Token
if ($function == 'check') {
    echo check_mail();
}
// if($function=='check'){
//     echo login();
// }
//forget password api 
if ($function == 'forget_password') {
    echo forget_password($username);
} //end

//get teacher profile image 
if ($function == 'get_teacher_image') {
    echo get_teacher_image($id);
}

if ($function == 'sign_up') {
    echo signUp();
}
if ($function == 'sign_up_new') {
    echo signUpNew($fname, $lname, $email, $email, $password, $phone1, $phone2, $role, $year, $city, $school, $center);
}
if ($function == 'signUpParent') {
    echo signUpParent();
}

if ($function == 'get_teacher_years') {
    echo get_teacher_years($teacherId);
}
if ($function == 'get_all_categories') {
    echo get_all_categories();
}
if ($function == 'get_all_news') {
    echo get_all_news();
}
if ($function == 'course_view_data') {
    echo course_view_data($courseId);
}
if ($function == 'get_promo_video') {
    echo get_promo_video($courseId);
}
if ($function == 'course_content') {
    echo course_content($courseId);
}
if ($function == 'get_course_contents_data') {
    echo get_course_contents_data($courseId, array());
}
if ($function == 'check_quiz_reviews') {
    echo check_quiz_reviews($quiz);
}
if ($function == 'get_enrolled_users_members') {
    echo get_enrolled_users_members($courseId, $option);
}
//check if token is valide or not 
if (!empty($token)) {

    $api = new webservice();
    $array = array();
    try {
        if (!empty($courseId) && ($function == "course_content_mobile") || ($function == "check_availability")) {
            $courseData = $DB->get_record('course', array('id' => $courseId));
            if (!empty($courseData->lang)) {
                $userCheck = $DB->get_record('external_tokens', array('token' => $token));
                $user = $DB->get_record('user', array('id' => $userCheck->userid));
                $upd = new stdClass();
                $upd->id = $user->id;
                $upd->lang = $courseData->lang;
                $DB->update_record('user', $upd);
            }
        }
        $array = $api->authenticate_user($token);
        if (!empty($array)) {
            $array = json_encode($api->authenticate_user($token));
            //echo $array;
            $arr = json_decode($array, true);
            $userID = $arr['user']['id'];

            //check user is student or teacher
            if ($function == 'check_isStudent') {
                echo check_isStudent($userID);
            }
            //get all teachers in database api 
            if ($function == 'teachers') {
                echo teachers();
            }

            //get all related courses by student year levele 
            if ($function == 'get_all_related_courses') {
                echo get_related_courses($year, $userID);
            }
            //get all student feedbacks 
            if ($function == 'get_user_feedbacks') {
                echo get_user_feedbacks($userID);
            }

            if ($function == 'get_enrol_courses') {
                echo get_enrol_courses($userID);
            }

            if ($function == 'teacher_data') {
                echo get_teacher_profile_data($id, $userID);
            }
            if ($function == "add_teacher_rating") {
                echo add_teacher_rating($userID, $id, $teacherRating);
            }
            if ($function == 'add_student_feedback') {
                echo add_student_feedback($userID, $teacherId, $courseId, $feedBackText);
            }
            if ($function == 'delete_student_feedback') {
                echo delete_student_feedback($feedBackId);
            }
            if ($function == 'upload_image') {
                echo upload_image($userID);
            }
            if ($function == 'create_group') {
                echo create_group($courseId, $name);
            }
            if ($function == 'add_group_members') {
                echo add_group_members($members, $groupID);
            }
            if ($function == 'delete_group') {
                echo delete_group($groupID);
            }
            if ($function == 'get_members') {
                echo get_members($groupID);
            }
            if ($function == "add_group_chat_member") {
                echo add_group_chat_member($userids, $name);
            }
            if ($function == 'remove_member') {
                echo remove_member($groupID, $members);
            }
            if ($function == 'remove_group_chat_member') {
                echo  remove_group_chat_member($userids, $name);
            }
            if ($function == 'add_teacher_images') {
                echo add_teacher_images($userID);
            }
            if ($function == 'delete_teacher_images') {
                echo delete_teacher_images($imageId);
            }
            if ($function == 'edit_user_data') {
                echo edit_user_data($userID, $userFirstName, $userlastName, $phone1, $phone2);
            }
            if ($function == 'add_teacher_videos') {
                echo add_teacher_videos($userID);
            }
            if ($function == 'delete_teacher_videos') {
                echo delete_teacher_videos($videoID);
            }
            if ($function == 'get_course_descriptions') {
                echo get_course_descriptions($courseId);
            }
            if ($function == 'get_user_name') {
                echo get_user_name($userID);
            }
            if ($function == 'search_user_by_course') {
                echo  search_user_by_course($email, $courseId);
            }
            if ($function == 'search_user') {
                echo search_user($email, $courseId);
            }
            if ($function == 'get_contact_requests_sent') {
                echo get_contact_requests_sent($userID, $requestedid);
            }
            if ($function == 'delete_contact_request') {
                echo delete_contact_request($userID, $requestedid);
            }
            if ($function == 'change_password') {
                echo change_password($userID);
            }
            if ($function == 'get_user_by_id') {
                echo get_user_by_id($id);
            }
            if ($function == 'create_conversation') {
                echo create_conversation($userids);
            }
            if ($function == 'create_group_conversation') {
                echo create_group_conversation($userids, $name);
            }
            if ($function == 'remove_conversation') {
                echo remove_conversation($id);
            }

            if ($function == 'get_h5p_result') {
                echo get_h5p_result($courseId);
            }
            if ($function == 'insert_course_reservation') {
                $userid = intval($userID);
                echo insert_course_reservation($userid, $courseId);
            }
            if ($function == 'is_course_reserved') {
                $userid = intval($userID);
                echo is_course_reserved($userid, $courseId);
            }
            if ($function == 'delete_course_reservation') {
                $userid = intval($userID);
                echo delete_course_reservation($userid, $courseId);
            }

            if ($function == 'all_course_reservations') {

                echo all_course_reservations();
            }
            if ($function == 'all_accept_course_reservations') {

                echo all_accept_course_reservations();
            }
            if ($function == 'accept_user_reservation') {

                echo accept_user_reservation($id, $courseId);
            }
            if ($function == 'get_courses_by_category') {

                echo get_courses_by_category($categoryId);
            }
            if ($function == 'create_child') {

                echo create_child($email, $userID, $phone1, 1);
            }
            if ($function == 'get_parent_data') {

                echo get_parent_data($userID);
            }
            if ($function == 'get_child_courses') {
                echo get_child_courses($id);
            }
            if ($function == 'add_to_cart') {
                echo add_to_cart($userID, $courseId);
            }
            if ($function == 'get_user_cart') {
                echo get_user_cart($userID, $token);
            }
            if ($function == 'remove_from_cart') {
                echo remove_from_cart($userID, $courseId);
            }
            if ($function == 'get_user_wallet_data') {
                echo get_user_wallet_data($userID);
            }
            if ($function == 'check_wallet_secret_key') {
                echo check_wallet_secret_key($userID, $secret);
            }
            if ($function == 'forget_wallet_secret') {
                echo forget_wallet_secret($userID, 0);
            }
            if ($function == 'check_course_in_the_cart_of_the_user') {
                echo check_course_in_the_cart_of_the_user($userID, $courseId);
            }
            if ($function == 'generate_new_secret') {
                echo generate_new_secret($userID, $code);
            }
            if ($function == 'get_total_price') {
                echo get_total_price($userID);
            }
            if ($function == 'enrol_student') {
                echo enrol_student($courseId, $userID, 5);
            }
            if ($function == 'enrol_student_courses') {
                echo enrol_student_courses($courseIDs, $userID);
            }
            if ($function == 'get_notifications') {
                echo get_user_notifications($userID);
            }
            if ($function == 'all_courses') {
                echo all_courses($userID);
            }

            if ($function == 'course_rate') {
                echo course_rate($userID, $courseId, $rate);
            }
            if ($function == 'course_content_mobile') {

                echo course_content_mobile($courseId, $userID);
            }
            if ($function == 'all_courses_by_category') {
                echo all_courses_by_category($userID, $categoryId);
            }
            if ($function == 'get_all_user_enrolled_courses') {
                echo get_all_user_enrolled_courses($userID);
            }
            if ($function == 'get_course_badges') {
                echo get_course_badges($courseId);
            }
            if ($function == 'get_badge_users') {
                echo get_badge_users($badgeId);
            }
            if ($function == 'get_session') {
                echo get_session($userID);
            }
            if ($function == 'unenroll_user') {
                echo unenroll_user($courseId, $userID);
            }
            if ($function == 'get_all_courses') {
                echo get_all_courses($userID, $id,$language);
            }
            if ($function == 'user_language_update') {
                echo user_language_update($userID, $language);
            }
            if ($function == 'check_device_code') {
                echo check_device_code($userID, $code, $activityid, $device, $deviceid);
            }
            if ($function == 'check_availability') {
                echo check_availability($courseId, $activityid, $userID);
            }
        } else
            echo json_encode(['message' => 'invalide token']);
    } catch (Exception $e) {
        echo json_encode(['message' => 'invalide token', "exception" => $e]);
    }
}

function aboutInfo()
{
    $string = "
    
    <p style='color:black;'> 
    طلابي الأعزاء كل عام وأنتم بخير .... 
    التطبيق محاولة جادة ومتخصصة لتفعيل النظام الجديد بكل تفاصيله بمعنى أنك ستتدرب في كل حصة على الامتحانات والأسئلة وطرق الحل الصحيحة والتي تحقق لك التفوق بإذن الله في النظام التعليمي الجديد .
    التطبيق ليس بنكا لتخزين الأسئلة والتدريبات ، ولكنه موقع تعليمي متكامل ( سنتر الكتروني ) يحقق كل أهداف العملية التعليمية وعلى رأسها التعود على المسائل بأشكالها المختلفة ، والمتابعة الجادة مني شخصيا ومن فريق العمل بشكل عام لكل إجاباتكم وحلولكم في جميع التقويمات 
    والهدف الأكبر للتطبيق أنه يجعلك على تواصل معي ومع فريق العمل على مدار 24 ساعة من خلال الأسئلة والاستفسارات المختلفة .
    سيكون بمقدورك أيضا من خلال التطبيق أن تقيم نفسك وتعرف مستواك الحقيقي من خلال الواجبات والاختبارات الجزئية والاختبارات العامة .
    طبعا سيكون الهاتف المحمول ( الموبايل ) والتاب الوسيلة التي نستخدمها في الاختبار الاسبوعي الذي سيكون في بداية كل حصة أو في المنزل في الموعد المحدد لكل الطلاب في نفس الوقت .
    تستطيع أيضا معرفة كل المعلومات المتعلقة بالمواعيد  ، وأماكن الحصص ، والحصص التعويضية ،  والدروس التي سيتم شرحها في كل حصة ، ومواعيد الاختبارات  الجزئية والعامة ، والواجبات الخاصة بكل حصة ، والتعرف على الملاحظات والتنبيهات المستجدة لأي سبب من الأسباب  .
    سيقوم التطبيق أيضا بعرض كل الأخبار والتعليمات الرسمية الخاصة بالنظام التعليمي الجديد والتي تقوم بإصدارها وزارة التربية والتعليم للصفين الأول الثانوي والثاني الثانوي.
    تستطيع أيضا عرض أي مشكلة خاصة بعدم قدرتك على التحصيل والفهم لأي نوع من أنواع المسائل وسوف أقوم شخصيا بمتابعة هذه الحالات ووضعها على الطريق الصحيح .
    أيضا يحقق التطبيق التواصل الفعال بين ولي الأمر وبيننا لتحقيق الصالح العام للطالب في المقام الأول . من خلال متابعة درجات الطالب ومتابعة الحضور والغياب . 
    سيقوم التطبيق أيضا بتصنيف الطلاب حسب المستوى ويمكنك من خلاله معرفة مستواك الحقيقي والعمل على الانتقال إلى مستوى أعلى .
    كما نقوم أيضا بعمل خطط لرفع مستوى الضعاف من خلال واجبات خاصة وتدريبات متميزة لتحسين المستوى .
    مع ملاحظة أن غياب ثلاث حصص يعرضك لغلق التطبيق والحرمان من خدماته بشكل كامل .
    على كل حال ... أهلا بكم  في هذا الملتقى التعليمي المتميز  لنتعاون جميعا من خلال منظومة إلكترونية متخصصة للوصول إلى أعلى الدرجات في مادة الرياضيات  مع مستر رضا القصاص
    </p>
    
    ";

    return json_encode(['about' => $string]);
}


function termsInfo()
{
    $arabicText = '<p style="color:white;font-size:18px;">  Privacy Policy
    Ahmed Ibrahim built Alaaby as a Free game. This SERVICE is provided by N.I.T Group at no cost and is intended for use as is.
    
    This page is used to inform visitors regarding my policies with the collection, use, and disclosure of Personal Information if anyone decided to use my Service.
    
    If you choose to use my Service, then you agree to the collection and use of information in relation to this policy. The Personal Information that I collect is used for providing and improving the Service. I will not use or share your information with anyone except as described in this Privacy Policy.
    
    The terms used in this Privacy Policy have the same meanings as in our Terms and Conditions, which is accessible at N.I.T Group unless otherwise defined in this Privacy Policy.
    
    Information Collection and Use
    
    For a better experience, while using our Service, I may require you to provide us with certain personally identifiable information. The information that I request will be retained on your device and is not collected by me in any way.
    
    Log Data
    
    I want to inform you that whenever you use my Service, in a case of an error in the game I collect data and information (through third party products) on your phone called Log Data. This Log Data may include information such as your device Internet Protocol address, device name, operating system version, the configuration of the game when utilizing my Service, the time and date of your use of the Service, and other statistics.
    
    Cookies
    
    Our game doesn\'t use any cookies.
    
    Service Providers
    
    I may employ third-party companies and individuals due to the following reasons:
    
    To facilitate our Service;
    To provide the Service on our behalf;
    To perform Service-related services; or
    To assist us in analyzing how our Service is used.
    I want to inform users of this Service that these third parties have access to your Personal Information. The reason is to perform the tasks assigned to them on our behalf. However, they are obligated not to disclose or use the information for any other purpose.
    
    Security
    
    I value your trust in providing us your Personal Information, thus we are striving to use commercially acceptable means of protecting it. But remember that no method of transmission over the internet, or method of electronic storage is 100% secure and reliable, and I cannot guarantee its absolute security.
    
    Links to Other Sites
    
    This Service may contain links to other sites. If you click on a third-party link, you will be directed to that site. Note that these external sites are not operated by me. Therefore, I strongly advise you to review the Privacy Policy of these websites. I have no control over and assume no responsibility for the content, privacy policies, or practices of any third-party sites or services.
    
    Children Privacy
    
    These Services do not address anyone under the age of 13. I do not knowingly collect personally identifiable information from children under 13. In the case I discover that a child under 13 has provided me with personal information, I immediately delete this from our servers. If you are a parent or guardian and you are aware that your child has provided us with personal information, please contact me so that I will be able to do necessary actions.
    
    Changes to This Privacy Policy
    
    I may update our Privacy Policy from time to time. Thus, you are advised to review this page periodically for any changes. I will notify you of any changes by posting the new Privacy Policy on this page. These changes are effective immediately after they are posted on this page.
    
    Contact Us
    
    If you have any questions or suggestions about my Privacy Policy, do not hesitate to contact me at ahmed.ncit@gmail.com.      
    </p>';
    $englishText  = '<p style="color:white;font-size:18px; "> Privacy Policy
        Ahmed Ibrahim built Alaaby as a Free game. This SERVICE is provided by N.I.T Group at no cost and is intended for use as is.
        
        This page is used to inform visitors regarding my policies with the collection, use, and disclosure of Personal Information if anyone decided to use my Service.
        
        If you choose to use my Service, then you agree to the collection and use of information in relation to this policy. The Personal Information that I collect is used for providing and improving the Service. I will not use or share your information with anyone except as described in this Privacy Policy.
        
        The terms used in this Privacy Policy have the same meanings as in our Terms and Conditions, which is accessible at N.I.T Group unless otherwise defined in this Privacy Policy.
        
        Information Collection and Use
        
        For a better experience, while using our Service, I may require you to provide us with certain personally identifiable information. The information that I request will be retained on your device and is not collected by me in any way.
        
        Log Data
        
        I want to inform you that whenever you use my Service, in a case of an error in the game I collect data and information (through third party products) on your phone called Log Data. This Log Data may include information such as your device Internet Protocol address, device name, operating system version, the configuration of the game when utilizing my Service, the time and date of your use of the Service, and other statistics.
        
        Cookies
        
        Our game doesn\'t use any cookies.
        
        Service Providers
        
        I may employ third-party companies and individuals due to the following reasons:
        
        To facilitate our Service;
        To provide the Service on our behalf;
        To perform Service-related services; or
        To assist us in analyzing how our Service is used.
        I want to inform users of this Service that these third parties have access to your Personal Information. The reason is to perform the tasks assigned to them on our behalf. However, they are obligated not to disclose or use the information for any other purpose.
        
        Security
        
        I value your trust in providing us your Personal Information, thus we are striving to use commercially acceptable means of protecting it. But remember that no method of transmission over the internet, or method of electronic storage is 100% secure and reliable, and I cannot guarantee its absolute security.
        
        Links to Other Sites
        
        This Service may contain links to other sites. If you click on a third-party link, you will be directed to that site. Note that these external sites are not operated by me. Therefore, I strongly advise you to review the Privacy Policy of these websites. I have no control over and assume no responsibility for the content, privacy policies, or practices of any third-party sites or services.
        
        Children Privacy
        
        These Services do not address anyone under the age of 13. I do not knowingly collect personally identifiable information from children under 13. In the case I discover that a child under 13 has provided me with personal information, I immediately delete this from our servers. If you are a parent or guardian and you are aware that your child has provided us with personal information, please contact me so that I will be able to do necessary actions.
        
        Changes to This Privacy Policy
        
        I may update our Privacy Policy from time to time. Thus, you are advised to review this page periodically for any changes. I will notify you of any changes by posting the new Privacy Policy on this page. These changes are effective immediately after they are posted on this page.
        
        Contact Us
        
        If you have any questions or suggestions about my Privacy Policy, do not hesitate to contact me at ahmed.ncit@gmail.com.      
        </p>';
    return json_encode(['arabic' => $arabicText, 'english' => $englishText]);
}

function change_password($userID)
{
    global $DB;
    $user = $DB->get_record('user', array("id" => $userID));
    if (!empty($user)) {
        $userPassword = $user->password;
        $oldPassword = $_GET["oldpassword"];
        $newPassword = $_GET["newpassword"];
        if (!empty($oldPassword) && !empty($newPassword)) {
            $reason = null;
            $usercheck = authenticate_user_login($user->username, $oldPassword, false, $reason, false);
            $userupdate = new stdClass();
            if (!empty($usercheck)) {
                $hashnewPassword = hash_internal_user_password($newPassword);
                $userupdate->id = $user->id;
                $userupdate->password = $hashnewPassword;
                $userupdate->id = $DB->update_record('user', $userupdate);
                if (!empty($userupdate->id)) {
                    return json_encode(['message' => 'passwordchanged']);
                }
            } else {
                return json_encode(['message' => 'incorrectpassword']);
            }
        }
    }
    return json_encode(['message' => 'passworderror']);
}

function signUp()
{
    global $DB;
    $userInfo = new stdClass();

    $yearInfo = new stdClass();
    $context = new stdClass();

    $yearMap = array("primary 1" => 1, "primary 2" => 2, "primary 3" => 3, "primary 4" => 4, "primary 5" => 5, "primary 6" => 6, "preparatory 1" => 7, "preparatory 2" => 8, "preparatory 3" => 9, "Secondary 1" => 10, "Secondary 2" => 11, "Secondary 3" => 12);

    try {

        $firstname = $_GET["firstname"];
        $lastname = $_GET["lastname"];
        $username = $_GET["username"];
        $email = $_GET["email"];
        $password = $_GET["password"];
        $uYear = $_GET["year"];
        $Phone = $_GET["studentPhone"];
        $parentPhone = $_GET["parentPhone"];
        //return json_encode([$firstname,$lastname,$username,$email,$uYear,$Phone,$parentPhone]);

    } catch (Exception $e) {
        echo json_encode(['message' => $e]);
    }

    if (!empty($firstname) && !empty($lastname) && !empty($username) && !empty($email) && !empty($password) && !empty($uYear) && !empty($Phone) && !empty($parentPhone)) {

        try {

            $getuserbyusername = $DB->get_record('user', array('username' => $username));
            if ($getuserbyusername != null) {
                return json_encode(["message" => "error usernameisused"]);
            }

            $getuserbyemail = $DB->get_record('user', array('email' => $email));
            if ($getuserbyemail != null) {
                return json_encode(["message" => "error emailisused"]);
            }


            $hashPass = hash_internal_user_password($password);

            $userInfo->firstname = $firstname;
            $userInfo->lastname = $lastname;
            $userInfo->username = $username;
            $userInfo->email = $email;
            $userInfo->password = $hashPass;
            $userInfo->phone1 = $Phone;
            $userInfo->phone2 = $parentPhone;
            $userInfo->confirmed = 1;
            $userInfo->mnethostid = 1;


            $userInfo->id = $DB->insert_record('user', $userInfo);

            $key = array_search(intval($uYear), $yearMap);

            $yearInfo->userid = $userInfo->id;
            $yearInfo->fieldid = 1;
            $yearInfo->data = $key;
            $yearInfo->dataformat = 0;


            $yearInfo->id = $DB->insert_record('user_info_data', $yearInfo);

            $context->contextlevel = 30;

            $context->instanceid = $userInfo->id;
            $context->depth = 2;
            $context->locked = 0;
            $context->id = $DB->insert_record('context', $context);
            $context->path = '/1/' . $context->id;
            $context->id = $DB->update_record('context', $context);

            $getContextid = $DB->get_record("context", array("instanceid" => $userInfo->id));

            $createSudent = new stdClass();
            $createSudent->roleid =  5;
            $createSudent->contextid = $getContextid->id;
            $createSudent->userid = $userInfo->id;
            $createSudent->modifierid = $userInfo->id;

            $createSudent->id = $DB->insert_record('role_assignments', $createSudent);


            return json_encode(["message" => "successful"]);
        } catch (Exception $e) {
            echo json_encode(['message' => $e]);
        }
    } else {
        return json_encode(["message" => "error empty"]);
    }
}

//signUp parant 

function signUpParent()
{
    global $DB;
    $userInfo = new stdClass();
    $context = new stdClass();
    try {

        $firstname = $_GET["firstname"];
        $lastname = $_GET["lastname"];
        $username = $_GET["username"];
        $email = $_GET["email"];
        $password = $_GET["password"];
        //$studentEmail = $_GET["studentEmail"];
        $parentPhone = $_GET["parentPhone"];
    } catch (Exception $e) {
        echo json_encode(['message' => $e]);
    }

    if (!empty($firstname) && !empty($lastname) && !empty($username) && !empty($email) && !empty($password) /*&& !empty($studentEmail)*/ && !empty($parentPhone)) {

        try {

            $getuserbyusername = $DB->get_record('user', array('username' => $username));
            if ($getuserbyusername != null) {
                return json_encode(["message" => "error usernameisused"]);
            }

            $getuserbyemail = $DB->get_record('user', array('email' => $email));
            if ($getuserbyemail != null) {
                return json_encode(["message" => "error emailisused"]);
            }

            //$selectStudent = $DB->get_record("user",array("email"=>$studentEmail));

            //if($selectStudent){

            //$selectStudent->id));

            //if($getContextid->id !== null){
            $hashPass = hash_internal_user_password($password);

            $userInfo->firstname = $firstname;
            $userInfo->lastname = $lastname;
            $userInfo->username = $username;
            $userInfo->email = $email;
            $userInfo->password = $hashPass;
            $userInfo->phone1 = $parentPhone;
            $userInfo->confirmed = 1;
            $userInfo->mnethostid = 1;


            $userInfo->id = $DB->insert_record('user', $userInfo);
            $get_user = $DB->get_record('user', array('username' => $userInfo->username));
            $context->contextlevel = 30;
            $context->instanceid = $get_user->id;
            $context->depth = 2;
            $context->locked = 0;
            $context->id = $DB->insert_record('context', $context);
            $context->path = '/1/' . $context->id;
            $context->id = $DB->update_record('context', $context);
            //$selectParentid = $DB->get_field('user', 'MAX(id)', array());
            $getContextid = $DB->get_record("context", array("instanceid" => $get_user->id));
            $createParent = new stdClass();
            $createParent->roleid =  9;
            $createParent->contextid = $getContextid->id;
            $createParent->userid = $get_user->id; //$selectParentid;
            $createParent->modifierid = $get_user->id; //$selectStudent->id;

            $create_result = $DB->insert_record('role_assignments', $createParent);

            if ($create_result) {
                //$get_user=$DB->get_record('user',array('username'=>$userInfo->username));
                //$res=create_child($studentEmail,$get_user->id,0);

                //if($res!='Error'){
                return json_encode(["message" => "successful"]);
                //}
            }
            //var_dump($createParent);
            //redirect($CFG->wwwroot."/login/index.php");


            /*}else{
                        return json_encode(["message"=> "error empty"]);
                    }
                
            /*}else{
                return json_encode(["message"=> "error empty"]);
            }*/
        } catch (Exception $e) {
            echo json_encode(['message' => $e]);
        }
    } else {

        return json_encode(["message" => "error empty"]);
    }
    return json_encode(["message" => "error empty"]);
}

//check if the user is a teacher or is a student

function check_isStudent($id)
{
    global $DB;
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $id, 'roleid' => $studentRole]);
    $teacherRole = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
    $isTeacher = $DB->record_exists('role_assignments', ['userid' => $id, 'roleid' => $teacherRole]);
    $parentRole = $DB->get_field('role', 'id', array('shortname' => 'parent'));
    $isParent = $DB->record_exists('role_assignments', ['userid' => $id, 'roleid' => $parentRole]);
    $admins = get_admins();
    $isadmin = false;
    foreach ($admins as $admin) {
        if ($id == $admin->id) {
            $isadmin = true;
            break;
        }
    }
    $roleassignments = $DB->get_records('role_assignments', ['userid' => $id]);
    $manager = false;
    foreach ($roleassignments as $role) {
        if ($role->roleid == 1) {
            $manager = true;
            break;
        }
    }

    if ($isadmin || $manager) {
        return json_encode(["message" => 'admin', 'id' => $id], 200);
    } elseif ($isStudent) {
        return json_encode(["message" => 'student', 'id' => $id], 200);
    } elseif ($isTeacher) {
        return json_encode(["message" => 'teacher', 'id' => $id], 200);
    } elseif ($isParent) {
        return json_encode(["message" => 'parent', 'id' => $id], 200);
    } else {
        return json_encode(["message" => 'Not Teacher Or a Student'], 200);
    }
}
//get all teachers in the site
function teachers()
{
    global $DB, $OUTPUT, $CFG;
    $array = array();
    $teachers = $DB->get_records_sql("SELECT DISTINCT u.*  FROM mdl_user as u INNER JOIN mdl_role_assignments as role ON role.userid=u.id and role.roleid=3");
    $fs = get_file_storage();
    foreach ($teachers as $teacher) {
        $context = context_user::instance($teacher->id);
        //$url=$CFG->wwwroot.'/pluginfile.php/'.$context->id.'/user/icon/edumy/f3?rev='.$teacher->picture.'';
        if (empty($teacher->url)) {
            $url = $CFG->wwwroot . '/pluginfile.php/' . $context->id . '/user/icon/edumy/f3?rev=' . $teacher->picture . '';
        } else {
            $url = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $teacher->url . '';
        }


        $teacher->src = $url;
        array_push($array, $teacher);
    }
    return json_encode(["message" => array_values($array)]);
}

function generate_random_code($username)
{
    global $DB, $OUTPUT, $CFG;

    $ins = new stdClass();
    $code = "";
    $record = $DB->get_record('random_code', array('username' => $username));
    if (empty($record)) {
        $ins->user = $record->id;
        $ins->code = random_string(10);
        $code = $ins->code;
        $ins->id = $DB->insert_record('random_code', $ins);
    } else {
        $ins->id = $record->id;
        $ins->user = $USER->id;
        $ins->code = random_string(10);
        $code = $ins->code;
        $ins->id = $DB->update_record('random_code', $ins);
    }
    return $code;
}

//forget password function 
function forget_password($username)
{
    global $DB, $OUTPUT, $CFG;
    $user = "";
    $userData = $DB->get_records_sql("SELECT * FROM mdl_user WHERE username='$username' or email='$username'");
    foreach ($userData as $userd) {
        $user = $userd;
    }

    // $user= $userData[0];

    // $code=generate_random_code($username);
    if (!empty($user)) {
        $phpmailer = new PHPMailer();
        $phpmailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.nitg-eg.com';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 26;
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->Username = 'noreply@nitg-eg.com';
        $phpmailer->Password = 'N.IT@202106';
        $phpmailer->setFrom('noreply@nitg-eg.com', 'Success Academy');
        $phpmailer->addAddress($user->email, $username);
        $phpmailer->Subject = 'Reset Password';
        $message = 'You should go to these link to change password <a href= ' . $CFG->wwwroot . '/json/confirm.php>Confirm Password</a>';
        $phpmailer->Body = $message;
        $phpmailer->IsHTML(true);
        if (!$phpmailer->send()) {
            return json_encode(["message" => "Error: " . $phpmailer->ErrorInfo]);

            // echo "Mailer Error: " . $phpmailer->ErrorInfo;
        } else {
            echo json_encode(["message" => "Sent"], true);
        }
    } else {
        echo json_encode(["message" => "username is not exist"]);
    }
}
function generate_random_string($length)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }

    return $randomString;
}



function login()
{
    echo  \core\session\manager::get_login_token();
}

//get related courses 
function get_related_courses($year, $id)
{
    global $DB, $OUTPUT, $CFG;
    $all_related_courses = $DB->get_records_sql("SELECT instanceid from mdl_customfield_data where value='$year' ");
    $array = array();
    foreach ($all_related_courses as $courses) {

        array_push($array, $courses->instanceid);
    }
    $arrayImploded = implode(", ", $array);

    $courses = core_course_external::get_courses_by_field('ids', $arrayImploded);
    return json_encode(["relatedCourses" => array_values($courses["courses"]), "warning_course" => array_values($courses["warnings"])]);
}

//get all user feedback 
function get_user_feedbacks($userID)
{
    global $DB, $OUTPUT, $CFG;
    $checkfeedback = $DB->get_records_sql("SELECT * FROM mdl_feedbacks WHERE user ='$userID'  ");
    $feedbacks = array();
    if (!empty($checkfeedback)) {
        foreach ($checkfeedback as $feedback) {
            $returnedusers = core_user_external::get_users_by_field(
                'id',
                array($feedback->teacher_id)
            );
            $feedback->teacher = $returnedusers[0];
            array_push($feedbacks, $feedback);
        }
        return json_encode(["feedbacks" => $feedbacks]);
    } else {
        return json_encode(["feedbacks" => "no feedbacks"]);
    }
}
function edit_description($text)
{
    $cleaner_input = strip_tags($text);
    return $cleaner_input;
}
function get_teacher_profile_data($teacherID, $userid)
{
    //teacher image link https://academy.nitg-eg.com/theme/edumy/images/teachers/656_f3.jpg
    global $DB, $OUTPUT, $CFG;
    //$userData = get_complete_user_data('id', $teacherID);
    $getTeacher = $DB->get_records_sql('SELECT * FROM mdl_user WHERE id=' . $teacherID . ' ');


    $userEnroledCourses = enrol_get_users_courses($teacherID);

    //$userEnroledCourses = core_enrol_external::get_users_courses($teacherID,true);
    //$rating=$DB->get_records_sql("SELECT ceil(AVG(rating)) as rating FROM `mdl_teacher_rating` WHERE teacher_id=$teacherID");
    $check_enrolled_user = 0;

    $teacherCourses = array();
    foreach ($userEnroledCourses as $course) {
        if ($check_enrolled_user != 1) {
            $context = context_course::instance($course->id, MUST_EXIST);
            $enrol = is_enrolled($context, $userid, '', true);
            if ($enrol) {
                $check_enrolled_user = 1;
            }
        }
        $courselist = new core_course_list_element($course);
        $overviewfiles = array();
        foreach ($courselist->get_course_overviewfiles() as $file) {
            $fileurl = moodle_url::make_webservice_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $overviewfiles[] = array(
                'filename' => $file->get_filename(),
                'fileurl' => $fileurl,
                'filesize' => $file->get_filesize(),
                'filepath' => $file->get_filepath(),
                'mimetype' => $file->get_mimetype(),
                'timemodified' => $file->get_timemodified(),
            );
        }
        $course->overviewfiles = $overviewfiles;

        $coursecontacts = array();
        foreach ($courselist->get_course_contacts() as $contact) {
            $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function ($role) {
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
            );
        }
        $course->contacts = $coursecontacts;
        $teacherCourses[] = $course;
    }
    //$userData->courses = array_values($userEnroledCourses);

    $getTeacher = array_values($getTeacher);
    $teachrerbio = $DB->get_record('user_info_data', array('userid' => $teacherID, 'fieldid' => 2));
    $getTeacher[0]->bio = edit_description($teachrerbio->data);
    if ($check_enrolled_user == 1) {

        $getTeacher[0]->enrolled = 'yes';
    } else {

        $getTeacher[0]->enrolled = 'no';
    }
    $getTeacher[0]->courses = $teacherCourses; //concatinate course with teacher data

    //attach feedbacks to teacher data
    $feedBacks = array();
    $checkfeedback = $DB->get_records_sql("SELECT * FROM mdl_feedbacks WHERE teacher_id ='$teacherID'  ");
    $checkfeedback = array_values($checkfeedback);
    foreach ($checkfeedback as $feedback) {
        $userData = get_complete_user_data('id', $feedback->user);
        $feedback->username = $userData->firstname;
        $feedback->userimage = $userData->url;
        $feedBacks[] = $feedback;
    }
    $getTeacher[0]->feedbacks = $feedBacks;

    // //attach photos
    $getTeacherPhotos = $DB->get_records_sql("SELECT * FROM mdl_teachersphotos WHERE teacher_id= '$teacherID'");
    $getTeacher[0]->photos = array_values($getTeacherPhotos);

    // //attach videos
    // // get videos for a teacher
    $getVideos = $DB->get_records_sql("SELECT* FROM mdl_vimeovedios WHERE teacher_id='$teacherID'");
    $getTeacher[0]->videos = array_values($getVideos);

    // //attach rating
    $rating = $DB->get_records_sql("SELECT ceil(AVG(rating)) as rating FROM `mdl_teacher_rating` WHERE teacher_id=$teacherID");
    $rating = array_values($rating);
    $getTeacher[0]->rating = $rating[0]->rating;

    return json_encode(['teacher' => $getTeacher[0]]);
}


function search_user_by_course($email, $courseId)
{
    global $DB, $OUTPUT, $CFG;

    //$user = $DB->get_records_sql("SELECT * FROM `mdl_user`WHERE `firstname` LIKE '%$email%' OR lastname LIKE '%$email%' ");

    $user = $DB->get_record('user', array('email' => $email));

    $userEnroledCourses = array();
    $userEnroledCourses = enrol_get_users_courses($user->id);
    $userEnroledCourses = array_values($userEnroledCourses);

    foreach ($userEnroledCourses as $course) {
        if ($course->id == $courseId) {
            return json_encode(["user" => $user]);
        }
    }
    return json_encode(["error" => 'no user found']);

    //return json_encode(["user" => array_values($user)]);

}

function get_user_by_id($userId)
{
    global $DB, $OUTPUT, $CFG;

    $user = $DB->get_record('user', array('id' => $userId));

    if (!empty($user)) {
        return json_encode(["user" => $user]);
    }
    return json_encode(["error" => 'no user found']);
}

function delete_contact_request($id, $requestedid)
{
    global $DB, $OUTPUT, $CFG;
    $data = $DB->delete_records('message_contact_requests', array('userid' => $id, 'requesteduserid' => $requestedid));
    if ($data == "true") {
        return json_encode(["message" => 'deleted']);
    } else {
        return json_encode(["message" => 'error']);
    }
    // $user=$DB->get_records_sql("SELECT * FROM mdl_message_contact_requests WHERE userid= '$id' and requesteduserid='$requestedid' ");
    // if(empty($user) ){
    //     return json_encode(["message"=> 'deleted']);
    // }
    // else{
    //     return json_encode(["message"=> 'error']);
    // }

}

function get_contact_requests_sent($id, $requestedid)
{
    global $DB, $OUTPUT, $CFG;
    $user = $DB->get_records_sql("SELECT * FROM mdl_message_contact_requests WHERE userid= '$id' and requesteduserid='$requestedid' ");

    if (empty($user)) {
        return json_encode(["message" => 'no']);
    } else {
        return json_encode(["message" => 'yes']);
    }
}

function get_enrol_courses($id)
{
    global $DB, $OUTPUT, $CFG;
    $i = 0;
    $userEnroledCourses = core_enrol_external::get_users_courses($id, true);
    //return json_encode(['teacher'=>$userEnroledCourses]);
    $EnroledCourses = enrol_get_users_courses($id);
    //return json_encode(["courses"=>$EnroledCourses]);
    $enrolCourses = array();
    foreach ($EnroledCourses as $course) {

        $courselist = new core_course_list_element($course);
        /*$overviewfiles = array();
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $fileurl = moodle_url::make_webservice_pluginfile_url($file->get_contextid(), $file->get_component(),
                                                                        $file->get_filearea(), null, $file->get_filepath(),
                                                                        $file->get_filename())->out(false);
                $overviewfiles[] = array(
                    'filename' => $file->get_filename(),
                    'fileurl' => $fileurl,
                    'filesize' => $file->get_filesize(),
                    'filepath' => $file->get_filepath(),
                    'mimetype' => $file->get_mimetype(),
                    'timemodified' => $file->get_timemodified(),
                );
            }
			$course->overviewfiles = $overviewfiles;*/

        $coursecontacts = array();
        foreach ($courselist->get_course_contacts() as $contact) {
            $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function ($role) {
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
            );
        }
        //$course->contacts = $coursecontacts;
        $userEnroledCourses[$i]['contacts'] = $coursecontacts;
        $i++;
        //$enrolCourses[] = $course;
    }

    return json_encode(["courses" => $userEnroledCourses]);
    //return json_encode(["courses"=>$enrolCourses]);
}

function get_child_courses($id)
{
    global $DB, $OUTPUT, $CFG;
    $i = 0;
    //$userEnroledCourses = core_enrol_external::get_users_courses($id,true);
    //return json_encode(['teacher'=>$userEnroledCourses]);
    $EnroledCourses = enrol_get_users_courses($id);
    //return json_encode(["courses"=>$EnroledCourses]);
    $enrolCourses = array();
    foreach ($EnroledCourses as $course) {

        $courselist = new core_course_list_element($course);
        $overviewfiles = array();
        foreach ($courselist->get_course_overviewfiles() as $file) {
            $fileurl = moodle_url::make_webservice_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $overviewfiles[] = array(
                'filename' => $file->get_filename(),
                'fileurl' => $fileurl,
                'filesize' => $file->get_filesize(),
                'filepath' => $file->get_filepath(),
                'mimetype' => $file->get_mimetype(),
                'timemodified' => $file->get_timemodified(),
            );
        }
        $course->overviewfiles = $overviewfiles;

        $coursecontacts = array();
        foreach ($courselist->get_course_contacts() as $contact) {
            $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function ($role) {
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
            );
        }
        $course->contacts = $coursecontacts;
        $enrolCourses[] = $course;
        //$enrolCourses[$i]['contacts'] = $coursecontacts;
        $i++;
        //$enrolCourses[] = $course;
    }

    //return json_encode(["courses"=>$userEnroledCourses]);
    return json_encode(["courses" => $enrolCourses]);
}

function add_teacher_rating($studentID, $teacherID, $rating)
{
    global $DB, $OUTPUT, $CFG;
    $ins = new stdClass();

    $ins->rating = $rating;

    $ins->teacher_id = $teacherID;
    $ins->user = $studentID;
    $record = $DB->get_record('teacher_rating', array('user' => $studentID, 'teacher_id' => $teacherID));
    if (empty($record)) {
        $ins->id = $DB->insert_record('teacher_rating', $ins);
        //return json_encode(['message'=>$ins]);
    } else {
        $ins->id = $record->id;
        $ins->id = $DB->update_record('teacher_rating', $ins);
    }

    return json_encode(['message' => 'ratingadded']);
}

function add_student_feedback($studentID, $teacherID, $courseID, $feedBack)
{
    global $DB, $OUTPUT, $CFG;

    $ins = new stdClass();
    $ins->feedback = $feedBack;
    $ins->title = 'feedBack';
    $ins->course = $courseID;
    $ins->teacher_id = $teacherID;
    $ins->user = $studentID;
    $ins->id = $DB->insert_record('feedbacks', $ins);

    return json_encode(['message' => 'feedbackadded']);
}

function delete_student_feedback($feedBackID)
{
    global $DB, $OUTPUT, $CFG;

    $ins = new stdClass();

    $ins->id = $DB->delete_records('feedbacks', array('id' => $feedBackID));

    return json_encode(['message' => 'feedbackdeleted']);
}

function add_teacher_images($id)
{
    global $DB, $OUTPUT, $CFG;
    $postImageName = $_FILES['image']['name'];
    $postImageTemp = $_FILES['image']['tmp_name'];
    $postImage = rand(0, 1000) . "_" . $postImageName;
    $uploadFiles = move_uploaded_file($postImageTemp, $CFG->dirroot . "/theme/edumy/images/teachers/" . $postImage);
    if ($uploadFiles) {
        $insertData = $DB->execute("INSERT INTO mdl_teachersphotos(teacher_id,photos) VALUE('$id', '$postImage')   ");

        return json_encode(['message' => 'image added']);
    } else {
        return json_encode(['message' => 'error']);
    }
}

function add_teacher_videos($id)
{
    global $DB, $OUTPUT, $CFG;
    $video = $_POST['videoLink'];
    $tag = '<div style="padding:55% 0 0 0;position:relative;">';
    $closingTag = "</div>";
    if (strpos($video, $closingTag) == true) {
        $video = str_replace($closingTag, " ", $video);
        $video = str_replace($tag, " ", $video);
    }
    $ins = new stdClass();
    $ins->videos =  $video;
    $ins->teacher_id = $id;
    $ins->id = $DB->insert_record('teachervideos', $ins);
    if ($ins) {
        return json_encode(['message' => 'video uploaded']);
    } else {
        return json_encode(['message' => 'error']);
    }
}

function delete_teacher_videos($id)
{
    global $DB, $OUTPUT, $CFG;
    $sql = "DELETE FROM mdl_vimeovedios WHERE id=$id";
    $DB->execute($sql);
    return json_encode(['message' => 'video deleted']);
}

function delete_teacher_images($id)
{
    global $DB, $OUTPUT, $CFG;
    $sql = "DELETE FROM mdl_teachersphotos WHERE id=$id";
    $DB->execute($sql);
    return json_encode(['message' => 'image deleted']);
}

function upload_image($id)
{
    global $DB, $OUTPUT, $CFG;
    $postImageName = $_FILES['image']['name'];
    $postImageTemp = $_FILES['image']['tmp_name'];
    $postImage = rand(0, 1000) . "_" . $postImageName;
    $uploadFiles = move_uploaded_file($postImageTemp, $CFG->dirroot . "/theme/edumy/images/teachers/" . $postImage);
    $checkUser = $DB->get_records_sql("SELECT * FROM mdl_user WHERE id = '$id'");
    if ($uploadFiles) {
        $DB->execute(" UPDATE mdl_user SET url= '$postImage' WHERE id = '$id' ");
        return json_encode(['message' => 'image uploaded']);
    } else {
        return json_encode(['message' => 'error']);
    }
}

function get_teacher_image($teacherid)
{
    global $DB, $OUTPUT, $CFG;
    $getTeacher = $DB->get_records_sql('SELECT url,firstname,lastname FROM mdl_user WHERE id=' . $teacherid . ' ');
    $getTeacher = array_values($getTeacher);

    $url = $CFG->wwwroot . '/theme/edumy/images/teachers/' . $getTeacher[0]->url . '';

    return json_encode(['image' => $url, 'fullname' => $getTeacher[0]->firstname . " " . $getTeacher[0]->lastname]);
}

function edit_user_data($userid, $firstname, $lastname, $phone1, $phone2)
{
    global $DB, $OUTPUT, $CFG;
    try {

        if (!empty($firstname)) {
            $DB->execute(" UPDATE mdl_user SET firstname= '$firstname' WHERE id = '$userid' ");
        }
        if (!empty($lastname)) {
            $DB->execute(" UPDATE mdl_user SET lastname= '$lastname' WHERE id = '$userid' ");
        }
        if (!empty($phone1)) {
            $DB->execute(" UPDATE mdl_user SET phone1= '$phone1' WHERE id = '$userid' ");
        }
        if (!empty($phone2)) {
            $DB->execute(" UPDATE mdl_user SET phone2= '$phone2' WHERE id = '$userid' ");
        }
    } catch (Exception $e) {
        return json_encode(['message' => 'error']);
    }

    return json_encode(['message' => 'done']);
}

function get_course_descriptions($coursId)
{
    global $DB, $OUTPUT, $CFG;
    $ins = new stdClass();

    $cDesc = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 15));
    $ins->courseDesc = $cDesc->value;

    $obj = array();
    $obj[0] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 2));
    $obj[1] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 3));
    $obj[2] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 4));
    $obj[3] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 5));
    $obj[4] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 6));
    $obj[5] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 7));
    $obj[6] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 8));
    $obj[7] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 9));
    $obj[8] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 10));
    $obj[9] = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 11));

    $coursePrice = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 12));
    $CourseDuration = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 13));
    $ins->coursePrice = $coursePrice->value;
    $ins->CourseDuration = $CourseDuration->value;

    $Allenrolusers = $DB->get_records_sql("SELECT count(*)as c From mdl_user_enrolments x,mdl_enrol y where x.enrolid=y.id and y.courseid='$coursId'");
    $Allenrolusers = array_values($Allenrolusers);
    $ins->allenrolusers = $Allenrolusers[0]->c;


    $objects = array();

    foreach ($obj as $element) {
        if ($element->value != null) {
            $ins_obj = new stdClass();
            $ins_obj->value = $element->value;
            array_push($objects, $ins_obj);
        }
    }
    $ins->objectives = $objects;

    $promo = $DB->get_record('customfield_data', array('instanceid' => $coursId, 'fieldid' => 22));
    if ($promo != null) {
        $ins->promo = 'true';
    } else {
        $ins->promo = 'false';
    }


    return json_encode(['message' => $ins]);
}

function get_user_name($userId)
{
    global $DB, $OUTPUT, $CFG;
    //$userData = get_complete_user_data('id', $teacherID);
    $getUser = $DB->get_records_sql('SELECT firstname,lastname,username,phone1,phone2 FROM mdl_user WHERE id=' . $userId . ' ');
    $center = $DB->get_record('optional_data_aibrahim', array('userid' => $userId));
    $getUser = array_values($getUser);
    $userData = $getUser[0];
    $userData->centername = $center->empty; //center name
    $userData->schooname = $center->school;
    //return json_encode(['data' => $getUser[0]]);
    return json_encode(['data' => $userData]);
}


function create_conversation($userids)
{
    global $DB, $OUTPUT, $CFG;
    //return \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL;
    sort($userids);
    $conversation = new stdClass();
    $conversation->convhash = null;
    $conversation->convhash = sha1(implode('-', $userids));
    if ($record = $DB->get_record('message_conversations', ['convhash' => $conversation->convhash])) {
        return json_encode([$record->id]);
    }
    $conversation->type = 1;
    $conversation->enabled = 1;
    $conversation->timecreated = time();
    $conversation->timemodified = $conversation->timecreated;
    $conversation->id = $DB->insert_record('message_conversations', $conversation);
    $arrmembers = [];
    foreach ($userids as $userid) {
        $member = new stdClass();
        $member->conversationid = $conversation->id;
        $member->userid = $userid;
        $member->timecreated = time();
        $member = $DB->insert_record('message_conversation_members', $member);

        $arrmembers[] = $member;
    }

    $conversation->members = $arrmembers;

    return json_encode([$conversation->id]);
}
function remove_conversation($conversationId)
{
    global $DB;
    $data = $DB->delete_records('message_conversations', array('id' => $conversationId));
    if ($data) {
        return 'deleted';
    } else {
        return 'error';
    }
}

function get_teacher_years($teacherId)
{
    global $DB, $OUTPUT, $CFG;
    $years = $DB->get_records_sql("SELECT * FROM mdl_teacher_years WHERE teacherID= '$teacherId' ");
    $years = array_values($years);
    return json_encode($years);
}

function get_h5p_result($courseId)
{
    global $DB, $OUTPUT, $CFG;
    $h5p = new stdClass();
    $h5p = $DB->get_records_sql("SELECT * FROM mdl_hvp WHERE course= '$courseId' ");
    $h5p = array_values($h5p);
    $id = $h5p[0]->id;
    $result = new stdClass();
    $result = $DB->get_records_sql("SELECT * FROM mdl_hvp_xapi_results WHERE content_id= '$id' ");
    return json_encode($result);
}

function insert_course_reservation($userId, $courseId)
{
    global $DB;

    $ins = new stdClass();
    $ins->userid = $userId;
    $ins->course = $courseId;
    //$ins->timecreated=date('Y-d-m H:i:s',time());
    // $yearMap=array("primary 1"=>1, "primary 2"=>2, "primary 3"=>3, "primary 4"=>4,"primary 5"=>5,"primary 6"=>6,"preparatory 1"=>7,"preparatory 2"=>8,"preparatory 3"=>9,"Secondary 1"=>10,"Secondary 2"=>11,"Secondary 3"=>12);
    $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
    $userYear = $DB->get_record('user_info_data', array('userid' => $userId, 'fieldid' => 1));
    $key = array_search($userYear->data, $yearMap);
    // return $key;
    $courseYear = $DB->get_record('customfield_data', array('instanceid' => $courseId, 'fieldid' => 1));
    // return $courseYear;
    if ($key == $courseYear->value) {
        $res = $DB->insert_record('course_reservation', $ins);
        if ($res) {
            return json_encode(["data" => 'Successfully']);
        }
    } else {
        return json_encode(["data" => 'NotAllowed']);
    }




    return json_encode(["data" => 'Error']);
}

function delete_course_reservation($userId, $courseId)
{
    global $DB;

    $res = $DB->delete_records('course_reservation', array('userid' => $userId, 'course' => $courseId));

    if ($res) {
        return json_encode(["data" => 'deleted']);
    }

    return json_encode(["data" => 'Error']);
}

function is_course_reserved($userId, $courseId)
{
    global $DB;

    $record = $DB->get_record('course_reservation', array('userid' => $userId, 'course' => $courseId, 'accept' => 0));

    if (!empty($record)) {
        return json_encode(["data" => 'true']);
    }

    return json_encode(["data" => 'false']);
}

function all_course_reservations()
{
    global $DB;
    //  $usersReserve = $DB->get_records_sql("SELECT courser.id,
    //                         user.id as userid,concat(user.firstname , ' ', user.lastname)as fullname ,user.url as image,user.phone1 as phone ,user.email,courser.course
    //                         FROM mdl_course_reservation courser
    //                         JOIN mdl_user user ON courser.userid = user.id
    //                         WHERE courser.accept=0 and user.deleted = 0

    //     ");
    $usersReserve = $DB->get_records_sql("SELECT courser.id,
    user.id as userid,concat(user.firstname , ' ', user.lastname)as fullname ,user.phone1 as phone ,user.email,courser.course
    FROM mdl_course_reservation courser
    JOIN mdl_user user ON courser.userid = user.id
    WHERE courser.accept=0 and user.deleted = 0

");

    $usersReserve = array_values($usersReserve);
    $final_data = array();

    foreach ($usersReserve as $user) {

        $teachers = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as teachername, c.fullname As coursename
                            FROM   mdl_course c
                            LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
                            LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
                            LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
                            WHERE cx.contextlevel = '50' AND c.id= '$user->course'");
        $teachers = array_values($teachers);
        $teacher = $teachers[0];

        $user->teachername = $teacher->teachername;
        $user->coursename = $teacher->coursename;

        array_push($final_data, $user);
    }


    return json_encode(['data' => $final_data]);
}

function all_accept_course_reservations()
{
    global $DB;

    //  $usersReserve = $DB->get_records_sql("SELECT courser.id,
    //                         user.id as userid,concat(user.firstname , ' ', user.lastname)as fullname ,user.url as image,user.phone1 as phone ,user.email,courser.course
    //                         FROM mdl_course_reservation courser
    //                         JOIN mdl_user user ON courser.userid = user.id
    //                         WHERE courser.accept=1 and user.deleted = 0

    //     ");
    $usersReserve = $DB->get_records_sql("SELECT courser.id,
    user.id as userid,concat(user.firstname , ' ', user.lastname)as fullname ,user.phone1 as phone ,user.email,courser.course
    FROM mdl_course_reservation courser
    JOIN mdl_user user ON courser.userid = user.id
    WHERE courser.accept=1 and user.deleted = 0

");
    $usersReserve = array_values($usersReserve);
    $final_data = array();

    foreach ($usersReserve as $user) {

        $teachers = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as teachername, c.fullname As coursename
                           FROM   mdl_course c
                           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
                           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
                           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
						   WHERE cx.contextlevel = '50' AND c.id= '$user->course'");
        $teachers = array_values($teachers);
        $teacher = $teachers[0];

        $user->teachername = $teacher->teachername;
        $user->coursename = $teacher->coursename;

        array_push($final_data, $user);
    }


    return json_encode(['data' => $final_data]);
}

function accept_user_reservation($userId, $courseId)
{
    global $DB;

    $record = $DB->get_record('course_reservation', array('userid' => $userId, 'course' => $courseId, 'accept' => 0));
    if (!empty($record)) {
        $res = new stdClass();
        $res->id = $record->id;
        $res->accept = 1;
        $state = $DB->update_record('course_reservation', $res);
        // return $response_end;
        if ($state) {
            return json_encode(["data" => 'Successfully']);
        }
    }

    return json_encode(["data" => 'error']);
}

function get_courses_by_category($categoryid)
{
    global $DB;
    $courses = $DB->get_records_sql("SELECT * FROM mdl_course WHERE category= $categoryid");
    $courses = array_values($courses);
    $final_data = array();
    $teacherCourses = array();
    foreach ($courses as $course) {

        $courselist = new core_course_list_element($course);
        $overviewfiles = array();
        foreach ($courselist->get_course_overviewfiles() as $file) {
            $fileurl = moodle_url::make_webservice_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $overviewfiles[] = array(
                'filename' => $file->get_filename(),
                'fileurl' => $fileurl,
                'filesize' => $file->get_filesize(),
                'filepath' => $file->get_filepath(),
                'mimetype' => $file->get_mimetype(),
                'timemodified' => $file->get_timemodified(),
            );
        }
        $course->overviewfiles = $overviewfiles;

        $coursecontacts = array();
        /*foreach ($courselist->get_course_contacts() as $contact) {
            $coursecontacts[] = array(
                'id' => $contact['user']->id,
                'fullname' => $contact['username'],
                'roles' => array_map(function($role){
                    return array('id' => $role->id, 'name' => $role->displayname);
                }, $contact['roles']),
                'role' => array('id' => $contact['role']->id, 'name' => $contact['role']->displayname),
                'rolename' => $contact['rolename']
            );
        }*/
        $teachers = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as fullname, c.fullname As coursename
                           FROM   mdl_course c
                           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
                           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
                           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
						   WHERE cx.contextlevel = '50' AND c.id= '$course->id'");
        $teachers = array_values($teachers);
        $teacher = $teachers[0];
        if (!empty($teacher->id)) {
            $coursecontacts[] = $teacher;
        }
        $course->contacts = $coursecontacts;
        $teacherCourses[] = $course;
    }
    return json_encode(['data' => $teacherCourses]);
}
function get_count_students($course)
{
    global $DB;
    $Allenrolusers = $DB->get_records_sql("SELECT u.id  from mdl_enrol as enroll join mdl_user_enrolments as ue on enroll.id=ue.enrolid
    join mdl_user as u on u.id=ue.userid join mdl_role_assignments as ra on u.id=ra.userid
    where ra.roleid=5 and enroll.courseid=" . $course . "
    GROUP by u.id");
    return count($Allenrolusers);
}
function create_child($email, $parentid, $phone1, $check = 0)
{
    global $DB;
    $parent = $DB->get_record('user', array('id' => $parentid));
    $user = $DB->get_record('user', array('email' => $email, 'phone1' => $phone1));
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $user->id, 'roleid' => $studentRole]);
    if ($isStudent) {
        $check_id = $DB->get_record('parent_child', array('parentid' => $parentid, 'childid' => $user->id));
        if (!$check_id) {
            if ($parent->phone1 == $user->phone2) {
                $ins = new stdClass();
                $ins->parentid = $parentid;
                $ins->childid = $user->id;
                $res = $DB->insert_record('parent_child', $ins);
                $getContextid = $DB->get_record("context", array("instanceid" => $user->id));
                $createParent = new stdClass();
                $createParent->roleid =  9;
                $createParent->contextid = $getContextid->id;
                $createParent->userid = $parentid; //$selectParentid;
                $createParent->modifierid = $user->id; //$selectStudent->id;
                $create_result = $DB->insert_record('role_assignments', $createParent);
                if ($create_result) {
                    if ($check == 0) {
                        return 'Successfully created';
                    } else {
                        return json_encode(['data' => 'Successfully']);
                    }
                }
            } else {
                return json_encode(['data' => 'Not your child']);
            }
        }
    } else {
        return json_encode(['data' => 'Error']);
    }
}

function get_parent_data($parent)
{
    global $DB;
    $childs = $DB->get_records_sql('SELECT  p.childid,u.firstname,u.lastname,u.url from mdl_parent_child as p join mdl_user as u on u.id=p.childid where parentid=' . $parent . '');
    return json_encode(['childs' => array_values($childs)]);
}

//carts
function add_to_cart($user, $course)
{
    global $DB;
    $data = new stdClass();
    $data->user = $user;
    $data->course = $course;
    $data->id = $DB->insert_record('cart', $data);
    if (empty($data->id)) {
        return json_encode(['data' => 'error']);
    } else {
        $ins = new stdClass();
        $ins->user = $user;
        $ins->course = $course;
        $ins->status = "user added the course to his cart";
        $DB->insert_record('history_cart_data', $ins);
        return json_encode(['data' => 'added']);
    }
}
function get_user_cart($user, $token = 0)
{
    global $DB, $CFG;
    $carts = $DB->get_records_sql("SELECT cart.id as id ,concat(u.firstname , ' ', u.lastname) As teachername , c.fullname as coursename ,cart.course as courseid ,u.id as teacherid ,cd.value as price
	FROM mdl_course as c 
  JOIN mdl_cart as cart ON cart.course=c.id 
  LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
  LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
  LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
 LEFT OUTER JOIN mdl_customfield_data cd ON c.id=cd.instanceid
    WHERE cx.contextlevel = '50'  AND cd.fieldid=17 AND cart.user=" . $user . "");
    // $final_data=array();
    $total = 0;
    foreach ($carts as $cart) {
        $course = $DB->get_record("course", array('id' => $cart->courseid));
        $courselist = new core_course_list_element($course);
        $overviewfiles = array();

        if ($token != 0) {
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $fileurl = moodle_url::make_webservice_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                $overviewfiles[] = array(
                    'filename' => $file->get_filename(),
                    'fileurl' => $fileurl,
                    'filesize' => $file->get_filesize(),
                    'filepath' => $file->get_filepath(),
                    'mimetype' => $file->get_mimetype(),
                    'timemodified' => $file->get_timemodified(),
                );
            }
            $cart->overviewfiles = $overviewfiles[0]['fileurl'] . "?token=" . $token;
            $total += $cart->price;
        } else {
            $url = "";
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $isimage = $file->is_valid_image();
                $url = file_encode_url("{$CFG->wwwroot}/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
            }
            $cart->overviewfiles = $url;
            $total += $cart->price;
        }
    }
    return json_encode(array('user_cart' => array_values($carts), 'items' => sizeof($carts), 'total' => $total));
}
function remove_from_cart($user, $course)
{
    global $DB;
    $data = $DB->delete_records('cart', array('user' => $user, 'course' => $course));
    if ($data) {
        $ins = new stdClass();
        $ins->user = $user;
        $ins->course = $course;
        $ins->status = "user removed the course to his cart";
        $DB->insert_record('history_cart_data', $ins);
        return json_encode(['data' => 'deleted']);
    } else {

        return json_encode(['data' => 'error']);
    }
}
function check_course_in_the_cart_of_the_user($user, $course)
{
    global $DB;
    $record = $DB->get_record('cart', array('user' => $user, 'course' => $course));
    if (!empty($record)) {
        return json_encode(['data' => 'true']);
    } else {
        return json_encode(['data' => 'false']);
    }
}
function get_user_wallet_data($user)
{
    global $DB;
    $wallet = $DB->get_record("wallet", array('user' => $user));
    if (empty($wallet)) {
        return json_encode(['data' => 'error:empty']);
    } else {
        return json_encode(['data' => $wallet]);
    }
}
function check_wallet_secret_key($user, $secret)
{
    global $DB;
    $wallet = $DB->get_record("wallet", array('user' => $user, 'secret' => $secret));
    if (empty($wallet)) {
        return json_encode(['error' => 'error:wrongSecret']);
    } else {
        return json_encode(['data' => $wallet]);
    }
}
function forget_wallet_secret($id, $confirm)
{
    global $DB;
    $user = $DB->get_record('user', array("id" => $id));
    // $code=generate_random_code($username);
    if (!empty($user)) {
        $phpmailer = new PHPMailer();
        $phpmailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.nitg-eg.com';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 26;
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->Username = 'noreply@nitg-eg.com';
        $phpmailer->Password = 'N.IT@202106';
        $phpmailer->setFrom('noreply@nitg-eg.com', 'Success Academy');
        $phpmailer->addAddress($user->email, $user->username);
        $phpmailer->Subject = 'Reset Password';
        // $message = 'You should go to these link to change password <a href= ' . $CFG->wwwroot . '/json/confirm.php>Confirm Password</a>';
        $code = '';
        if ($confirm == 0) {
            $code = generate_random_string(5);
            $message = "Your code is : " . $code . " you can go again to the form and write the code then the new secret key will be generated";
        } else {
            $data = $DB->get_record('wallet', array('user' => $id));
            $message = "Your new Secret is : " . $data->secret . " . Now,you can go open your wallet";
        }
        $phpmailer->Body = $message;
        $phpmailer->IsHTML(true);
        if (!$phpmailer->send()) {
            echo "Mailer Error: " . $phpmailer->ErrorInfo;
        } else {
            if ($confirm == 0) {
                $record = $DB->get_record('secret_user_wallet_code', array('user' => $user->id));
                if (empty($record)) {
                    $ins = new stdClass();

                    $ins->user = $user->id;
                    $ins->code = $code;
                    $ins->id = $DB->insert_record('secret_user_wallet_code', $ins);
                } else {
                    $ins = new stdClass();
                    $ins->id = $record->id;
                    $ins->code = $code;
                    $data = $DB->update_record('secret_user_wallet_code', $ins);
                }
            }


            return json_encode(["message" => 'sent'], true);
        }
    } else {


        return json_encode(["message" => "username is not exist"]);
    }
}
function generate_new_secret($user, $code)
{
    global $DB;
    $check_code = $DB->get_record('secret_user_wallet_code', array('user' => $user, 'code' => $code));
    if (empty($check_code)) {
        return json_encode(["data" => "error:wrongcode"]);
    } else {
        $newSecret = generate_random_string(50);
        $data = json_decode(forget_wallet_secret($user, 1));
        if ($data->message == "sent") {
            $secret = $DB->get_record('wallet', array('user' => $user));
            $ins = new stdClass();
            $ins->id = $secret->id;
            $ins->secret = $newSecret;
            $DB->update_record('wallet', $ins);
            return json_encode(["message" => 'done'], true);
        }
    }
}
function get_total_price($id)
{
    global $DB;
    $carts = $DB->get_records_sql("SELECT SUM(cd.value) as price
	FROM mdl_course as c 
  JOIN mdl_cart as cart ON cart.course=c.id 
  LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
  LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
  LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
 LEFT OUTER JOIN mdl_customfield_data cd ON c.id=cd.instanceid
    WHERE cx.contextlevel = '50'  AND cd.fieldid=17 AND cart.user=" . $id . "");
    $price = 0;
    foreach ($carts as $cart) {
        $price = $cart->price;
    }
    return json_encode(["data" => $price], true);
}
function create_group($courseId, $name)
{
    global $DB;

    $groupdata = new stdClass();
    $groupdata->courseid = $courseId;
    $groupdata->name = $name;

    $id = groups_create_group($groupdata);

    $group = $DB->get_record('groups', array('id' => $id), '*', MUST_EXIST);
    $group->enablemessaging = 1;
    $group->enrolmentkey = "";
    return json_encode(["data" => $group]);
}
function add_group_members($members, $groupID)
{

    foreach ($members as $member) {
        groups_add_member($groupID, $member);
    }
    return "added";
}
function delete_group($groupID)
{

    groups_delete_group($groupID);

    return "done";
}
function get_members($groupID)
{
    global $DB;
    $members = array();
    /*
    SELECT $fields
                                   FROM {user} u
                                     INNER JOIN {groups_members} gm ON u.id = gm.userid
                                     INNER JOIN {groupings_groups} gg ON gm.groupid = gg.groupid
                                  WHERE  gg.groupingid = ?
                               ORDER BY $sort", array($groupingid));
    */

    $members = $DB->get_records_sql("SELECT u.*
  FROM mdl_user u
    INNER JOIN mdl_groups_members gm ON u.id = gm.userid WHERE  gm.groupid =   $groupID ORDER BY u.id ASC");
    $members = array_values($members);
    return json_encode(["data" => $members]);
}
function remove_member($groupID, $members)
{
    foreach ($members as $member) {
        groups_remove_member($groupID, $member);
    }

    return "done";
}

function create_group_conversation($userids, $name)
{
    global $DB, $OUTPUT, $CFG;
    //return \core_message\api::MESSAGE_CONVERSATION_TYPE_INDIVIDUAL;
    sort($userids);
    $conversation = new stdClass();
    $conversation->convhash = null;
    $conversation->convhash = sha1(implode('-', $userids));
    $conversation->name = $name;
    if ($record = $DB->get_record('message_conversations', ['name' => $conversation->name])) {
        $message = [
            [
                'text' => "Welcome",
                'textformat' => FORMAT_MOODLE
            ],
        ];
        core_message_external::send_messages_to_conversation($record->id, $message);
        return json_encode([$record->id]);
    }
    $conversation->type = 2;
    $conversation->enabled = 1;
    $conversation->timecreated = time();
    $conversation->timemodified = $conversation->timecreated;
    $conversation->id = $DB->insert_record('message_conversations', $conversation);
    $arrmembers = [];
    foreach ($userids as $userid) {
        $member = new stdClass();
        $member->conversationid = $conversation->id;
        $member->userid = $userid;
        $member->timecreated = time();
        $member = $DB->insert_record('message_conversation_members', $member);

        $arrmembers[] = $member;
    }

    $conversation->members = $arrmembers;
    $message = [
        [
            'text' => "Welcome",
            'textformat' => FORMAT_MOODLE
        ],
    ];
    core_message_external::send_messages_to_conversation($conversation->id, $message);
    return json_encode([$conversation->id]);
}
function add_group_chat_member($userids, $name)
{
    global $DB;
    sort($userids);
    $conversation = new stdClass();
    $conversation->name = $name;
    $conversation = $DB->get_record('message_conversations', ['name' => $conversation->name]);
    $arrmembers = [];
    foreach ($userids as $userid) {
        $member = new stdClass();
        $member->conversationid = $conversation->id;
        $member->userid = $userid;
        $member->timecreated = time();
        $member = $DB->insert_record('message_conversation_members', $member);

        $arrmembers[] = $member;
    }
    $conversation->members = $arrmembers;
    return "added";
}
function remove_group_chat_member($userids, $name)
{
    global $DB;
    sort($userids);
    $conversation = new stdClass();
    $conversation->name = $name;
    $conversation = $DB->get_record('message_conversations', ['name' => $conversation->name]);
    $arrmembers = [];
    foreach ($userids as $userid) {
        $member = new stdClass();
        $member->conversationid = $conversation->id;
        $member->userid = $userid;
        $record = $DB->get_record('message_conversation_members', ['userid' => $userid, 'conversationid' => $conversation->id]);
        if (!empty($record)) {
            $DB->delete_records('message_conversation_members', array('id' => $record->id));
        }
    }

    return "deleted";
}


// core_group_external::add_group_members([
//     'members' => [
//         'groupid' => $groupID,
//         'userid' => $member,
//     ]
// ]);
function  enrol_student_courses($courseIDs, $userid)
{
    global $DB;
    $finalResult = 'false';
    foreach ($courseIDs as $item) {
        $result = enrol_student($item, $userid, 5);
        if ($result == 'true') {
            $data = $DB->delete_records('cart', array('user' => $userid, 'course' => $item));
            $finalResult = 'true';
        }
    }
    return json_encode(["data" => $finalResult]);
}

function enrol_student($id, $userid, $roleid, $enrolmethod = 'manual')
{
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0), '*', MUST_EXIST);
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $user->id, 'roleid' => $studentRole]);
    try {
        if ($isStudent) {
            $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
            $context = context_course::instance($course->id);
            if (!is_enrolled($context, $user)) {
                $enrol = enrol_get_plugin($enrolmethod);
                if ($enrol === null) {
                    return 'false';
                }
                $instances = enrol_get_instances($course->id, true);
                $manualinstance = null;
                foreach ($instances as $instance) {
                    if ($instance->enrol == $enrolmethod) {
                        $manualinstance = $instance;
                        break;
                    }
                }
                if ($manualinstance == null) {
                    $instanceid = $enrol->add_default_instance($course);
                    if ($instanceid === null) {
                        $instanceid = $enrol->add_instance($course);
                    }
                    $instance = $DB->get_record('enrol', array('id' => $instanceid));
                }
                $enrol->enrol_user($instance, $userid, $roleid);
            }
            return 'true';
        }
    } catch (Exception $e) {
        return 'false';
    }
}

function get_user_notifications($userid)
{
    global $DB;

    $notifications = $DB->get_records_sql("SELECT * FROM `mdl_notifications` WHERE useridto = $userid and timeread is  null");
    $notifications = array_values($notifications);
    $notificount = count($notifications);
    return json_encode(["data" => $notifications, 'count' => $notificount]);
}

// function get_all_categories()
// {
//     global $DB;

//     $data = $DB->get_records_sql("SELECT * FROM `mdl_course_categories` where visible=1 and coursecount>0" );

//     $categories = array_values($data);
//     return json_encode(["data" => $categories]);
// }


function get_all_news()
{
    global $DB;
    $data = $DB->get_records_sql("SELECT * FROM `mdl_newsslider`");
    $news = array_values($data);

    return json_encode(["data" => $news]);
}

//MANPOWER APIS
// function edit_description($text)
// {
//     $cleaner_input = strip_tags($text);
//     return $cleaner_input;
// }
function getBetween($string, $start = "", $end = "")
{
    if (strpos($string, $start)) { // required if $start not exist in $string
        $startCharCount = strpos($string, $start) + strlen($start);
        $firstSubStr = substr($string, $startCharCount, strlen($string));
        $endCharCount = strpos($firstSubStr, $end);
        if ($endCharCount == 0) {
            $endCharCount = strlen($firstSubStr);
        }
        return substr($firstSubStr, 0, $endCharCount);
    } else {
        return '';
    }
}
function get_all_categories()
{
    global $DB;

    $data = $DB->get_records_sql("SELECT name,id,description FROM `mdl_course_categories` where visible=1 and coursecount>0");
    $categories = array();
    foreach ($data as $cat) {
        // $deletecat = core_course_category::get($cat->id, MUST_EXIST);
        // $deletecat = core_course_category::;
        // $name='';
        // if (preg_match('/{malng ar} (.*?) {mlang}/', $cat->name, $match) == 1) {
        //     $name= $match[1];
        // }
        // $name=getBetween($cat->name,'{malng ar}','{mlang}');
        $name = format_string($cat->name);
        $desc = edit_description($cat->description);
        $categories[] = array('id' => $cat->id, 'name' => $name, 'description' => $desc);
    }
    // $categories = array_values($data);
    return json_encode(["data" => $categories]);
}
function all_courses($user)
{
    global $DB, $CFG;
    $courses = $DB->get_records_sql("SELECT co.id as courseId ,co.visible as visible ,co.fullname as courseName,co.summary as courseDesc,co.category as catId,cat.name as catName FROM `mdl_course` as co join mdl_course_categories as cat ON co.category=cat.id ");
    $data_courses = array_values($courses);
    $coursesData = array();
    $coursecontacts = array();
    $teacherId = -1;
    $teacherName = "";

    foreach ($data_courses as $course) {
        if ($course->visible) {
            $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
            if (empty($price)) {
                $price = 'free';
            }
            $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course->courseid));
            $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
            $rating = array_values($rating);

            // $inCart = 'false';
            // $record = $DB->get_record('cart', array('user' => $user, 'course' => $course->courseid));
            // if (!empty($record)) {
            //    $inCart='true';
            // } 

            $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
            $imges = $DB->get_record("course", array('id' => $course->courseid));
            $courselist = new core_course_list_element($imges);
            $context = context_course::instance($course->courseid, MUST_EXIST);
            $enrol = is_enrolled($context, $user, '', true);
            if ($enrol) {
                $enrol = 'true';
            } else {
                $enrol = 'false';
            }
            $url = "";
            // foreach ($courselist->get_course_overviewfiles() as $file) {
            //     $isimage = $file->is_valid_image();
            //     $url = file_encode_url("{$CFG->wwwroot}/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
            // }
            $overviewfiles = array();
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $fileurl = moodle_url::make_webservice_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                $overviewfiles[] = array(
                    'filename' => $file->get_filename(),
                    'fileurl' => $fileurl,
                    'filesize' => $file->get_filesize(),
                    'filepath' => $file->get_filepath(),
                    'mimetype' => $file->get_mimetype(),
                    'timemodified' => $file->get_timemodified(),
                );
            }
            $overviewfiles = $overviewfiles[0]['fileurl'];
            $teachers = $DB->get_records_sql("SELECT u.firstname AS name,u.lastname AS lastname, u.url as picture,u.description,u.email,ra.contextid,u.id As id, c.fullname as coursename
            FROM   mdl_course c
           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id= '$course->courseid';");
            foreach ($teachers as $teacher) {
                $teacherId = $teacher->id;
                $teacherName = $teacher->name . ' ' . $teacher->lastname;
            }
            $course->coursedesc = edit_description($course->coursedesc);
            if (empty($teacherId)) {
                $teacherId = -1;
                $teacherName = null;
            }
            // $coursesData[] = array('course_id' => $course->courseid, 'course_name' => $course->coursename,
            //  'enrol' => $enrol, 'course_desc' => $courseDescription->value,
            //   'views' => $view->visit, 'teacherId' => $teacherId,
            //    'teacherName' => $teacherName, 'price' => $price->value, 'image' => $url,
            //    'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
            //    );


            $coursesData[] = array(
                'course_id' => $course->courseid, 'course_name' => $course->coursename,
                'enrol' => $enrol, 'course_desc' => $courseDescription->value,
                'views' => $view->visit, 'teacherId' => $teacherId,
                'teacherName' => $teacherName, 'image' => $overviewfiles, 'price' => $price->value,
                'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
            );
        }
    }

    return json_encode(["data" => $coursesData]);
}
// function all_courses($user)
// {
//     global $DB, $CFG;
//     $courses = $DB->get_records_sql("  ");
//     $data_courses = array_values($courses);
//     $coursesData = array();
//     $coursecontacts = array();
//     $teacherId = -1;
//     $teacherName = "";
//     var_dump($courses);

//     foreach ($data_courses as $course) {

//         $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
//         if (empty($price)) {
//             $price = 'free';
//         }
//         return $price;
//         $courseDescription=$DB->get_record('customfield_data',array('fieldid' => 15, 'instanceid' => $course->courseid));
//         $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
//         $rating = array_values($rating);

//         // $inCart = 'false';
//         // $record = $DB->get_record('cart', array('user' => $user, 'course' => $course->courseid));
//         // if (!empty($record)) {
//         //    $inCart='true';
//         // } 

//         $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
//         $imges = $DB->get_record("course", array('id' => $course->courseid));
//         $courselist = new core_course_list_element($imges);
//         $context = context_course::instance($course->courseid, MUST_EXIST);
//         $enrol = is_enrolled($context, $user, '', true);
//         if ($enrol) {
//             $enrol = 'true';
//         } else {
//             $enrol = 'false';
//         }
//         $url = "";
//         foreach ($courselist->get_course_overviewfiles() as $file) {
//             $isimage = $file->is_valid_image();
//             $url = file_encode_url("{$CFG->wwwroot}/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
//         }

//         foreach ($courselist->get_course_contacts() as $contact) {
//             $teacherId = $contact['user']->id;
//             $teacherName = $contact['username'];

//             break;
//         }
//         $course->coursedesc = edit_description($course->coursedesc);

//         $coursesData[] = array('course_id' => $course->courseid, 'course_name' => $course->coursename, 'enrol' => $enrol, 'course_desc' => $courseDescription->value,'rate'=>$rating[0]->rate,'views'=>$view->visit,'teacherId' => $teacherId, 'teacherName' => $teacherName, 'price' => $price->value, 'image' => $url, 'cat_id' => $course->catid, 'cat_name' => $course->catname);
//     }

//     return json_encode(["data" => $coursesData]);
// }

function get_course_contents_data($courseid, $options = array())
{
    global $CFG, $DB;
    require_once($CFG->dirroot . "/course/lib.php");
    require_once($CFG->libdir . '/completionlib.php');

    //validate parameter


    $filters = array();


    //retrieve the course
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

    if ($course->id != SITEID) {
        // Check course format exist.
        if (!file_exists($CFG->dirroot . '/course/format/' . $course->format . '/lib.php')) {
            throw new moodle_exception(
                'cannotgetcoursecontents',
                'webservice',
                '',
                null,
                get_string('courseformatnotfound', 'error', $course->format)
            );
        } else {
            require_once($CFG->dirroot . '/course/format/' . $course->format . '/lib.php');
        }
    }

    // now security checks
    $context = context_course::instance($course->id, IGNORE_MISSING);




    // $canupdatecourse = has_capability('moodle/course:update', $context);

    //create return value
    $coursecontents = array();

    if ($course->visible) {

        //retrieve sections
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $courseformat = course_get_format($course);
        $coursenumsections = $courseformat->get_last_section_number();
        $stealthmodules = array();   // Array to keep all the modules available but not visible in a course section/topic.

        $completioninfo = new completion_info($course);

        //for each sections (first displayed to last displayed)
        $modinfosections = $modinfo->get_sections();
        foreach ($sections as $key => $section) {

            // This becomes true when we are filtering and we found the value to filter with.
            $sectionfound = false;

            // Filter by section id.
            if (!empty($filters['sectionid'])) {
                if ($section->id != $filters['sectionid']) {
                    continue;
                } else {
                    $sectionfound = true;
                }
            }

            // Filter by section number. Note that 0 is a valid section number.
            if (isset($filters['sectionnumber'])) {
                if ($key != $filters['sectionnumber']) {
                    continue;
                } else {
                    $sectionfound = true;
                }
            }

            // reset $sectioncontents
            $sectionvalues = array();
            $sectionvalues['id'] = $section->id;
            $sectionvalues['name'] = get_section_name($course, $section);
            $sectionvalues['visible'] = $section->visible;

            $options = (object) array('noclean' => true);
            list($sectionvalues['summary'], $sectionvalues['summaryformat']) =
                external_format_text(
                    $section->summary,
                    $section->summaryformat,
                    $context->id,
                    'course',
                    'section',
                    $section->id,
                    $options
                );
            $sectionvalues['section'] = $section->section;
            $sectionvalues['hiddenbynumsections'] = $section->section > $coursenumsections ? 1 : 0;
            $sectionvalues['uservisible'] = $section->uservisible;
            if (!empty($section->availableinfo)) {
                $sectionvalues['availabilityinfo'] = \core_availability\info::format_info($section->availableinfo, $course);
            }

            $sectioncontents = array();

            // For each module of the section.
            if (empty($filters['excludemodules']) and !empty($modinfosections[$section->section])) {
                foreach ($modinfosections[$section->section] as $cmid) {
                    $cm = $modinfo->cms[$cmid];

                    // Stop here if the module is not visible to the user on the course main page:
                    // The user can't access the module and the user can't view the module on the course page.
                    if (!$cm->uservisible && !$cm->is_visible_on_course_page()) {
                        continue;
                    }

                    // This becomes true when we are filtering and we found the value to filter with.
                    $modfound = false;

                    // Filter by cmid.
                    if (!empty($filters['cmid'])) {
                        if ($cmid != $filters['cmid']) {
                            continue;
                        } else {
                            $modfound = true;
                        }
                    }

                    // Filter by module name and id.
                    if (!empty($filters['modname'])) {
                        if ($cm->modname != $filters['modname']) {
                            continue;
                        } else if (!empty($filters['modid'])) {
                            if ($cm->instance != $filters['modid']) {
                                continue;
                            } else {
                                // Note that if we are only filtering by modname we don't break the loop.
                                $modfound = true;
                            }
                        }
                    }

                    $module = array();

                    $modcontext = context_module::instance($cm->id);

                    //common info (for people being able to see the module or availability dates)
                    $module['id'] = $cm->id;
                    $module['name'] = $cm->name;
                    $module['instance'] = $cm->instance;
                    $module['modname'] = (string) $cm->modname;
                    $module['modplural'] = (string) $cm->modplural;
                    $module['modicon'] = $cm->get_icon_url()->out(false);
                    $module['indent'] = 0;
                    $module['onclick'] = $cm->onclick;
                    $module['afterlink'] = $cm->afterlink;
                    $module['customdata'] = json_encode($cm->customdata);
                    $module['completion'] = $cm->completion;
                    $module['noviewlink'] = plugin_supports('mod', $cm->modname, FEATURE_NO_VIEW_LINK, false);
                    $free_activity = $DB->get_record('local_metadata', array('fieldid' => 1, 'instanceid' => $cm->id));
                    $paid = "";
                    if ($free_activity->data == "Yes") {
                        $paid = "No";
                    } else if ($free_activity->data == "No" || empty($free_activity)) {
                        $paid = "Yes";
                    }
                    $module['paid'] = $paid;
                    $avail = $modinfo->get_cm($cm->id);
                    $module['avail'] = $avail->available;
                    if ($module['avail'] == false) {
                        // $module['avail_message'] = strip_tags(\core_availability\info::format_info($cm->availableinfo, $course));
                        // if (!empty($cm->availableinfo)) {
                        //     $module['availabilityinfo'] = \core_availability\info::format_info($cm->availableinfo, $course);
                        // }

                        // // Availability date (also send to user who can see hidden module).
                        // if ($CFG->enableavailability && ($canviewhidden || $canupdatecourse)) {
                        //     $module['availability'] = $cm->availability;
                        // }
                        $reason1 = json_encode($avail->availableinfo);
                        $reason = json_decode($reason1);
                        $string = '';
                        if (gettype($reason) == 'string') {

                            $module['avail_message'] = strip_tags(\core_availability\info::format_info($cm->availableinfo, $course));
                        } else {
                            for ($i = 0; $i < count($reason->items); $i++) {
                                // array_push($module['avail_message'], $reason->items[$i]->text." <br>");
                                $string .= strip_tags($reason->items[$i]) . ",";
                            }
                            $string = substr($string, 0, -1);

                            $module['avail_message'] = $string;
                        }
                        // if (gettype($reason) == 'string') {
                        //     if (strpos(strip_tags($reason), 'Enrolled') !== false || strpos(strip_tags($reason), 'انضم') !== false) {
                        //         $module['avail_message'][0] = "enrollerror";
                        //     }
                        //    elseif  (strpos(strip_tags($reason), 'week') !== false || strpos(strip_tags($reason), 'اسبوع') !== false) {
                        //         $module['avail_message'][0] = "codeerror";
                        //     } else {
                        //     $module['avail_message'][0] = strip_tags($reason);
                        //     }
                        // } else {
                        //     for ($i = 0; $i < count($reason->items); $i++) {
                        //         if (strpos(strip_tags($reason->items[$i]), 'Enrolled') !== false || strpos(strip_tags($reason), 'انضم') !== false) {
                        //             $module['avail_message'][$i] = "enrollerror";
                        //         }
                        //         elseif (strpos(strip_tags($reason->items[$i]), 'week') !== false || strpos(strip_tags($reason), 'اسبوع') !== false) {
                        //             $module['avail_message'][$i] = "codeerror";
                        //         } else {
                        //             $module['avail_message'][$i] = strip_tags($reason->items[$i]);
                        //         }
                        //     }
                        // }
                    }
                    $module['quiz_type'] = '';

                    if ($cm->modname == "quiz") {
                        $quizData = $DB->get_record('quiz', array('id' => $cm->instance));
                        if ($quizData->reviewspecificfeedback == 0 && $quizData->reviewoverallfeedback == 0 && $quizData->reviewgeneralfeedback == 0 && $quizData->reviewcorrectness == 0 && $quizData->reviewrightanswer == 0) {
                            $module['quiz_type'] = 'exam';
                        } else {
                            $module['quiz_type'] = 'quiz';
                        }
                    }
                    $module['resource_type'] = "";
                    if ($cm->modname == "resource") {
                        $video_type = $DB->get_record('reda_video_type', array('resource_id' => $cm->instance));
                        if ($video_type->type == "2") {
                            $module['resource_type'] = "lesson";
                        } else {
                            $module['resource_type'] = "quiz";
                        }
                    }
                    $module['page_url'] = "";

                    if ($cm->modname == "page") {
                        $pageData = $DB->get_record('page', array('id' => $cm->instance));
                        //    preg_match('~"(.*?)"~', $pageData->content, $output);
                        //      $module['page_url'] = $output[1];
                        preg_match_all('/(src|value)="([^"]+)"/', $pageData->content, $matches);
                        //    preg_match('~"(.*?)"~', $pageData->content, $output);
                        $module['page_url'] = $matches[2][0];
                    }



                    // Check module completion.
                    $completion = $completioninfo->is_enabled($cm);
                    if ($completion != COMPLETION_DISABLED) {
                        $completiondata = $completioninfo->get_data($cm, true);
                        $module['completiondata'] = array(
                            'state'         => $completiondata->completionstate,
                            'timecompleted' => $completiondata->timemodified,
                            'overrideby'    => $completiondata->overrideby,
                            'valueused'     => core_availability\info::completion_value_used($course, $cm->id)
                        );
                    }

                    if (!empty($cm->showdescription) or $module['noviewlink']) {
                        // We want to use the external format. However from reading get_formatted_content(), $cm->content format is always FORMAT_HTML.
                        $options = array('noclean' => true);
                        list($module['description'], $descriptionformat) = external_format_text(
                            $cm->content,
                            FORMAT_HTML,
                            $modcontext->id,
                            $cm->modname,
                            'intro',
                            $cm->id,
                            $options
                        );
                    }

                    //url of the module
                    $url = $cm->url;
                    if ($url) { //labels don't have url
                        $module['url'] = $url->out(false);
                    }

                    $canviewhidden = has_capability(
                        'moodle/course:viewhiddenactivities',
                        context_module::instance($cm->id)
                    );
                    //user that can view hidden module should know about the visibility
                    $module['visible'] = $cm->visible;
                    $module['visibleoncoursepage'] = $cm->visibleoncoursepage;
                    $module['uservisible'] = $cm->uservisible;
                    // if (!empty($cm->availableinfo)) {
                    //     $module['availabilityinfo'] = \core_availability\info::format_info($cm->availableinfo, $course);
                    // }

                    // Availability date (also send to user who can see hidden module).
                    if ($CFG->enableavailability && ($canviewhidden)) {
                        $module['availability'] = $cm->availability;
                    }

                    // Return contents only if the user can access to the module.
                    if ($cm->uservisible) {
                        $baseurl = 'webservice/pluginfile.php';

                        // Call $modulename_export_contents (each module callback take care about checking the capabilities).
                        require_once($CFG->dirroot . '/mod/' . $cm->modname . '/lib.php');
                        $getcontentfunction = $cm->modname . '_export_contents';
                        if (function_exists($getcontentfunction)) {
                            $contents = $getcontentfunction($cm, $baseurl);
                            $module['contentsinfo'] = array(
                                'filescount' => count($contents),
                                'filessize' => 0,
                                'lastmodified' => 0,
                                'mimetypes' => array(),
                            );
                            foreach ($contents as $content) {
                                // Check repository file (only main file).
                                if (!isset($module['contentsinfo']['repositorytype'])) {
                                    $module['contentsinfo']['repositorytype'] =
                                        isset($content['repositorytype']) ? $content['repositorytype'] : '';
                                }
                                if (isset($content['filesize'])) {
                                    $module['contentsinfo']['filessize'] += $content['filesize'];
                                }
                                if (
                                    isset($content['timemodified']) &&
                                    ($content['timemodified'] > $module['contentsinfo']['lastmodified'])
                                ) {

                                    $module['contentsinfo']['lastmodified'] = $content['timemodified'];
                                }
                                if (isset($content['mimetype'])) {
                                    $module['contentsinfo']['mimetypes'][$content['mimetype']] = $content['mimetype'];
                                }
                            }

                            if (empty($filters['excludecontents']) and !empty($contents)) {
                                $module['contents'] = $contents;
                            } else {
                                $module['contents'] = array();
                            }
                        }
                    }

                    // Assign result to $sectioncontents, there is an exception,
                    // stealth activities in non-visible sections for students go to a special section.
                    if (!empty($filters['includestealthmodules']) && !$section->uservisible && $cm->is_stealth()) {
                        $stealthmodules[] = $module;
                    } else {
                        $sectioncontents[] = $module;
                    }

                    // If we just did a filtering, break the loop.
                    if ($modfound) {
                        break;
                    }
                }
            }
            $sectionvalues['modules'] = $sectioncontents;

            // assign result to $coursecontents
            $coursecontents[$key] = $sectionvalues;

            // Break the loop if we are filtering.
            if ($sectionfound) {
                break;
            }
        }

        // Now that we have iterated over all the sections and activities, check the visibility.
        // We didn't this before to be able to retrieve stealth activities.
        foreach ($coursecontents as $sectionnumber => $sectioncontents) {
            $section = $sections[$sectionnumber];
            // Show the section if the user is permitted to access it OR
            // if it's not available but there is some available info text which explains the reason & should display OR
            // the course is configured to show hidden sections name.
            $showsection = $section->uservisible ||
                ($section->visible && !$section->available && !empty($section->availableinfo)) ||
                (!$section->visible && empty($courseformat->get_course()->hiddensections));

            if (!$showsection) {
                unset($coursecontents[$sectionnumber]);
                continue;
            }

            // Remove section and modules information if the section is not visible for the user.
            if (!$section->uservisible) {
                $coursecontents[$sectionnumber]['modules'] = array();
                // Remove summary information if the section is completely hidden only,
                // even if the section is not user visible, the summary is always displayed among the availability information.
                if (!$section->visible) {
                    $coursecontents[$sectionnumber]['summary'] = '';
                }
            }
        }

        // Include stealth modules in special section (without any info).
        if (!empty($stealthmodules)) {
            $coursecontents[] = array(
                'id' => -1,
                'name' => '',
                'summary' => '',
                'summaryformat' => FORMAT_MOODLE,
                'modules' => $stealthmodules
            );
        }
    }
    return $coursecontents;
}
function course_content($course)
{
    global $DB, $CFG;
    $free = '';
    $activitiesCount = 0;
    $teacherId = -1;
    $teacherName = '';
    $teacherBio = '';
    $promo = 'no';
    $promoId = '0';
    $courseRate = '0';
    $coursesObj = array();
    $courseData = $DB->get_record('course', array('id' => $course));
    $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course));
    $check_type = $DB->get_record('customfield_data', array('fieldid' => 28, 'instanceid' => $course));
    $requirements = $DB->get_record('customfield_data', array('fieldid' => 23, 'instanceid' => $course));
    $forwhom = $DB->get_record('customfield_data', array('fieldid' => 24, 'instanceid' => $course));
    $benefits = $DB->get_record('customfield_data', array('fieldid' => 25, 'instanceid' => $course));
    $language = $DB->get_record('customfield_data', array('fieldid' => 26, 'instanceid' => $course));
    $certificate = $DB->get_record('customfield_data', array('fieldid' => 27, 'instanceid' => $course));
    $objectives = $DB->get_records_sql('SELECT * FROM `mdl_customfield_data`WHERE `instanceid`=' . $course . ' and (`fieldid`=2 or `fieldid`=3 or `fieldid`=4 or `fieldid`=5 or `fieldid`=6 or `fieldid`=7 or `fieldid`=8 or `fieldid`=9 or `fieldid`=10)');
    $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course));
    $view = $DB->get_record('course_views', array('courseid' => $course));
    $Allenrolusers = $DB->get_records_sql("SELECT count(*)as c From mdl_user_enrolments x,mdl_enrol y where x.enrolid=y.id and y.courseid='$course'");
    $Allenrolusers = array_values($Allenrolusers);
    $allenrolusers = $Allenrolusers[0]->c;
    $numberOfModules = $DB->get_records_sql("SELECT count(*)as c From mdl_course_modules where course='$course'");
    $numberOfModules = array_values($numberOfModules);
    $numberOfModules = $numberOfModules[0]->c;
    $contents = get_course_contents_data($course, array());
    // $course_content = json_decode($contents, true);

    foreach ($contents as $content) {
        $modules = $content['modules'];

        $activitiesCount += count($modules);
    }
    if (empty($price)) {
        $price = '';
    }
    if (empty($check_type) || $check_type->value == 0) {
        $free = "no";
    } else {
        $free = "yes";
    }

    if (empty($certificate) || $check_type->value == 0) {
        $certificate = "no";
    } else {
        $certificate = "yes";
    }
    if ($view->visit == null) {
        $view->visit = '0';
    }
    $objectives = array_values($objectives);
    foreach ($objectives as $obj) {
        if (!empty($obj->value)) {
            $coursesObj[] = array('value' => $obj->value);
        }
    }
    $courselist = new core_course_list_element($courseData);
    foreach ($courselist->get_course_contacts() as $contact) {
        $teacherId = $contact['user']->id;
        $teacherName = $contact['username'];
        break;
    }
    if ($teacherId != -1) {
        $teacherBio = $DB->get_record('user_info_data', array('userid' => $teacherId, 'fieldid' => 3));
    }
    $enddate = date("Y-m-d", $courseData->enddate);
    $startdate = date("Y-m-d", $courseData->startdate);
    $duration = ($enddate - $startdate) * 365;
    if ($duration < 0) {
        $duration = '';
    }
    // $get_cms = $DB->get_records_sql("SELECT id from mdl_course_modules where course=" . $course . " and module=18 and deletioninprogress=0 ");
    // foreach ($get_cms as $gcm) {
    //     $promo_check = $DB->get_record('local_metadata', array('instanceid' => $gcm->id, 'fieldid' => 3));

    //     if ($promo_check->data == "Yes") {
    //         $promo = 'yes';
    //         $activitiesCount--;
    //         $promoId = $gcm->id;
    //         break;
    //     }
    // }
    $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course");
    $rating = array_values($rating);
    $context = context_course::instance($course, MUST_EXIST);
    $user_context = context_user::instance($teacherId, MUST_EXIST);
    $fs = get_file_storage();
    $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
    $image = '';
    if (count($files) < 1) {
        $image = 'not_set';
    } else {
        $file = reset($files);
        unset($files);
        $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
        $image = $CFG->wwwroot . '/pluginfile.php' . $path;
    }
    // $user=$DB->get_record('user',array('id'=>$teacherId));
    if (empty($teacherId)) {
        $teacherId = null;
        $teacherName = null;
    }
    $customFields = array(
        'courseid' => $course, 'coursename' => $courseData->fullname,
        'free' => $free, 'requirements' => $requirements->value,
        'forwhom' => $forwhom->value, 'benefits' => $benefits->value, 'language' => $language->value,
        'certificate' => $certificate, 'objectives' => $coursesObj, 'description' => $courseDescription->value,
        'price' => $price->value, 'views' => $view->visit, 'allenrolusers' => $allenrolusers,
        'activitiesCount' => $activitiesCount, 'teacherId' => $teacherId, 'teacherName' => $teacherName,
        'teacherBio' => $teacherBio->data, 'duration' => $duration,
        'courseRate' => $rating[0]->rate, 'teacherImage' => $image,
        'contents' => $contents
    );
    // $customFields = array(
    //     'free' => $free,

    //     'objectives' => $coursesObj, 'description' => $courseDescription->value,
    //     'price' => $price->value, 'allenrolusers' => $allenrolusers,
    //     'activitiesCount' => $activitiesCount, 'teacherId' => $teacherId, 'teacherName' => $teacherName,
    //      'duration' => $duration, 'promo' => $promo,
    //      'image' => $image,
    //     'contents' => $contents
    // );


    return json_encode(["data" => $customFields], true);
}
function course_content_mobile($course, $userid)
{
    global $DB, $CFG;
    $free = '';
    $activitiesCount = 0;
    $teacherId = -1;
    $teacherName = '';
    $teacherBio = '';
    $promo = 'no';
    $promoId = '0';
    $courseRate = '0';
    $coursesObj = array();
    $courseData = $DB->get_record('course', array('id' => $course));
    $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course));

    $check_type = $DB->get_record('customfield_data', array('fieldid' => 28, 'instanceid' => $course));
    $requirements = $DB->get_record('customfield_data', array('fieldid' => 23, 'instanceid' => $course));
    $forwhom = $DB->get_record('customfield_data', array('fieldid' => 24, 'instanceid' => $course));
    $benefits = $DB->get_record('customfield_data', array('fieldid' => 25, 'instanceid' => $course));
    $language = $DB->get_record('customfield_data', array('fieldid' => 26, 'instanceid' => $course));
    $certificate = $DB->get_record('customfield_data', array('fieldid' => 27, 'instanceid' => $course));
    $objectives = $DB->get_records_sql('SELECT * FROM `mdl_customfield_data`WHERE `instanceid`=' . $course . ' and (`fieldid`=2 or `fieldid`=3 or `fieldid`=4 or `fieldid`=5 or `fieldid`=6 or `fieldid`=7 or `fieldid`=8 or `fieldid`=9 or `fieldid`=10)');
    $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course));
    $view = $DB->get_record('course_views', array('courseid' => $course));
    $Allenrolusers = $DB->get_records_sql("SELECT count(*)as c From mdl_user_enrolments x,mdl_enrol y where x.enrolid=y.id and y.courseid='$course'");
    $Allenrolusers = array_values($Allenrolusers);
    $allenrolusers = $Allenrolusers[0]->c;
    $numberOfModules = $DB->get_records_sql("SELECT count(*)as c From mdl_course_modules where course='$course'");
    $numberOfModules = array_values($numberOfModules);
    $numberOfModules = $numberOfModules[0]->c;

    $contents = get_course_contents_data($course, array());
    // $course_content = json_decode($contents, true);

    foreach ($contents as $content) {
        $modules = $content['modules'];

        $activitiesCount += count($modules);
    }
    if (empty($price)) {
        $price = '';
    }
    if (empty($check_type) || $check_type->value == 0) {
        $free = "no";
    } else {
        $free = "yes";
    }

    if (empty($certificate) || $check_type->value == 0) {
        $certificate = "no";
    } else {
        $certificate = "yes";
    }
    if ($view->visit == null) {
        $view->visit = '0';
    }

    $objectives = array_values($objectives);
    foreach ($objectives as $obj) {
        if (!empty($obj->value)) {
            $coursesObj[] = array('value' => $obj->value);
        }
    }
    $courselist = new core_course_list_element($courseData);
    foreach ($courselist->get_course_contacts() as $contact) {
        $teacherId = $contact['user']->id;
        $teacherName = $contact['username'];
        break;
    }
    if ($teacherId != -1) {
        $teacherBio = $DB->get_record('user_info_data', array('userid' => $teacherId, 'fieldid' => 2));
    }
    $enddate = date("Y-m-d", $courseData->enddate);
    $startdate = date("Y-m-d", $courseData->startdate);
    $duration = ($enddate - $startdate) * 365;
    if ($duration < 0) {
        $duration = '';
    }
    $video = $DB->get_record('promo', array('course' => $course));
    if (!empty($video)) {
        $promo = 'yes';
    }

    // $inCart = 'false';
    // $record = $DB->get_record('cart', array('user' => $userid, 'course' => $course));
    // if (!empty($record)) {
    //    $inCart='true';
    // } 
    // $get_cms = $DB->get_records_sql("SELECT id from mdl_course_modules where course=" . $course . " and module=18 and deletioninprogress=0 ");
    // foreach ($get_cms as $gcm) {
    //     $promo_check = $DB->get_record('local_metadata', array('instanceid' => $gcm->id, 'fieldid' => 3));

    //     if ($promo_check->data == "Yes") {
    //         $promo = 'yes';
    //         $activitiesCount--;
    //         $promoId = $gcm->id;
    //         break;
    //     }
    // }

    $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course");
    $rating = array_values($rating);
    $context = context_course::instance($course, MUST_EXIST);
    $enrol = is_enrolled($context, $userid, '', true);
    if ($enrol) {
        $enrol = "enrolled";
    } else {
        $enrol = "not_enrolled";
    }
    $user_context = context_user::instance($teacherId, MUST_EXIST);
    $fs = get_file_storage();
    $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
    $image = '';
    $user = $DB->get_record('user', array('id' => $teacherId));
    if (!empty($user->url)) {
        $image = '' . $CFG->wwwroot . '/theme/edumy/images/teachers/' . $user->url . '';
    } else {
        $image = '';
    }
    // if (count($files) < 1) {
    //     $image = 'not_set';
    // } else {
    //     $file = reset($files);
    //     unset($files);
    //     $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
    //     $image = $CFG->wwwroot . '/pluginfile.php' . $path;
    // }

    // $user=$DB->get_record('user',array('id'=>$teacherId));
    if (empty($teacherId)) {
        $teacherId = null;
        $teacherName = null;
    }
    $assisstantRole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
    $isAssisstant = $DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $assisstantRole]);
    $assistant = "false";
    if ($isAssisstant) {
        $assistant = "true";
    }
    $customFields = array(
        'courseid' => $course, 'coursename' => $courseData->fullname,
        'free' => $free, 'requirements' => $requirements->value,
        'forwhom' => $forwhom->value, 'benefits' => $benefits->value, 'language' => $language->value,
        'certificate' => $certificate, 'objectives' => $coursesObj, 'description' => $courseDescription->value,
        'price' => $price->value, 'views' => $view->visit, 'allenrolusers' => $allenrolusers,
        'activitiesCount' => $activitiesCount, 'teacherId' => $teacherId, 'teacherName' => $teacherName,
        'teacherBio' => edit_description($teacherBio->data), 'duration' => $duration, 'promo' => $promo,
        'courseRate' => $rating[0]->rate, 'teacherImage' => $image, 'enrol' => $enrol, "assistant" => $assistant,
        'contents' => $contents
    );
    //     $customFields = array(
    //         'courseid'=>$course,'coursename'=>$courseData->fullname,
    //         'free' => $free, 
    //          'objectives' => $coursesObj, 'description' => $courseDescription->value,
    //         'price' => $price->value, 'allenrolusers' => $allenrolusers,
    //         'activitiesCount' => $activitiesCount, 'teacherId' => $teacherId, 'teacherName' => $teacherName,
    //        'duration' => $duration, 'promo' => $promo,
    //  'teacherImage' => $image, 'enrol' => $enrol,
    //         'contents' => $contents
    //     );


    return json_encode(["data" => $customFields], true);
}
function course_view_data($course)
{
    global $DB;
    $isIt = $DB->record_exists('course_views', array('courseid' => $course));
    if ($isIt) {
        $visits = $DB->get_record('course_views', array('courseid' => $course), $fields = '*');

        $newvisit = $visits->visit + 1;


        $upduser = new stdClass();
        $upduser->id = $visits->id;
        $upduser->visit = $newvisit;
        $DB->update_record('course_views', $upduser);
        return json_encode(["data" => 'done']);
    } else {
        $ins = new stdClass();
        $ins->courseid = $course;
        $ins->visit = 1;
        $ins->id = $DB->insert_record('course_views', $ins);
        return json_encode(["data" => 'done']);
    }
}

function course_rate($userid, $course, $rate)
{
    global $DB;
    $context = context_course::instance($course, MUST_EXIST);
    $enrol = is_enrolled($context, $userid, '', true);
    if ($enrol) {
        $userExist = $DB->record_exists('course_rates', array('userid' => $userid,  'courseid' => $course));
        if ($userExist) {
            $userRate = $DB->get_record('course_rates', array('userid' => $userid, 'courseid' => $course), $fields = '*');
            $updRate = new stdClass();
            $updRate->id = $userRate->id;
            $updRate->rate = $rate;
            $DB->update_record('course_rates', $updRate);
            return json_encode(["data" => 'done']);
        } else {

            $ins = new stdClass();
            $ins->courseid = $course;
            $ins->userid = $userid;
            $ins->rate = $rate;
            $ins->id = $DB->insert_record('course_rates', $ins);
            return json_encode(["data" => 'done']);
        }
    } else {
        return json_encode(["data" => 'notenroled']);
    }
}

/*
1- check if promo activity exist or not 
2- if exist promoVar =true
else promoVar = false
*/
function get_promo_video($course)
{
    global $DB, $CFG;
    $promo = 'no';
    $promoId = 0;
    $get_cms = $DB->get_records_sql("SELECT id from mdl_course_modules where course=" . $course . " and module=18 and deletioninprogress=0 ");
    foreach ($get_cms as $gcm) {
        $promo_check = $DB->get_record('local_metadata', array('instanceid' => $gcm->id, 'fieldid' => 3));

        if ($promo_check->data == "Yes") {
            $promo = 'yes';
            $promoId = $gcm->id;
            break;
        }
    }
    if ($promo == 'yes') {
        $get_resource = $DB->get_record('course_modules', array('id' => $promoId));
        $resource = $DB->get_record('resource', array('id' => $get_resource->instance));
        $context = context_module::instance($promoId);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
        if (count($files) < 1) {
            $get_vimeo_data = $DB->get_record('vimeo_files', array('resource_id' => $get_resource->instance));
            $client = new Vimeo("4dad588b7f47a44426afc26f398fe2367ea49c92", "IHRxCFjq5qvsKlU6DjWGfNQwtZGHGmK1pByyCYWGrkWnE9F91BbNqPdqXY+dHVyvKjvRWYTu3ba2A8KM1GR2gcqqYiz+jXAx6uLrsEb0jFJrUSMIi3KMIyS+Je+nsN3s", "195c95a4e775fca8d6e70cb8db4aca73");
            $uri = "/videos/" . $get_vimeo_data->url;
            $response = $client->request($uri, [], 'GET');
            $response = $response['body']['embed']['html'];
            if (!empty($response)) {
                return json_encode(["data" => $response]);
            } else {
                return json_encode(["data" => 'notfound']);
            }
        } else {
            $file = reset($files);
            unset($files);
            $path = '/' . $context->id . '/mod_resource/content/' . $resource->revision . $file->get_filepath() . $file->get_filename();
            $fullurl = $CFG->wwwroot . '/pluginfile.php' . $path;
            $fullurl = str_replace(' ', '%20', $fullurl);
            //    $data=$DB->get_record('course',array('id'=>$course));
            // $data=resource_display_frame($resource, $get_resource, $data, $file);
            // return $data;
            return json_encode(["data" => $fullurl]);
        }
    }
}
function all_courses_by_category($user, $categoryid)
{
    global $DB, $CFG;
    $courses = $DB->get_records_sql("SELECT co.id as courseId ,co.visible as visible ,co.fullname as courseName,co.summary as courseDesc,co.category as catId,cat.name as catName FROM `mdl_course` as co join mdl_course_categories as cat ON co.category=cat.id  where co.category=" . $categoryid . "");
    $data_courses = array_values($courses);
    $coursesData = array();
    $coursecontacts = array();
    $teacherId = -1;
    $teacherName = "";

    foreach ($data_courses as $course) {
        if ($course->visible) {
            $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
            if (empty($price)) {
                $price = 'free';
            }
            $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course->courseid));

            $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
            $rating = array_values($rating);

            // $inCart = 'false';
            // $record = $DB->get_record('cart', array('user' => $user, 'course' => $course->courseid));
            // if (!empty($record)) {
            //    $inCart='true';
            // } 

            $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
            $imges = $DB->get_record("course", array('id' => $course->courseid));
            $courselist = new core_course_list_element($imges);
            $context = context_course::instance($course->courseid, MUST_EXIST);
            $enrol = is_enrolled($context, $user, '', true);
            if ($enrol) {
                $enrol = 'true';
            } else {
                $enrol = 'false';
            }
            $url = "";
            $overviewfiles = array();
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $fileurl = moodle_url::make_webservice_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                $overviewfiles[] = array(
                    'filename' => $file->get_filename(),
                    'fileurl' => $fileurl,
                    'filesize' => $file->get_filesize(),
                    'filepath' => $file->get_filepath(),
                    'mimetype' => $file->get_mimetype(),
                    'timemodified' => $file->get_timemodified(),
                );
            }
            $overviewfiles = $overviewfiles[0]['fileurl'];
            $teachers = $DB->get_records_sql("SELECT u.firstname AS name,u.lastname AS lastname, u.url as picture,u.description,u.email,ra.contextid,u.id As id, c.fullname as coursename
            FROM   mdl_course c
           LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
           LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
           LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id= '$course->courseid';");
            foreach ($teachers as $teacher) {
                $teacherId = $teacher->id;
                $teacherName = $teacher->name . ' ' . $teacher->lastname;
            }
            $course->coursedesc = edit_description($course->coursedesc);
            if (empty($teacherId)) {
                $teacherId = -1;
                $teacherName = null;
            }
            $course->coursedesc = edit_description($course->coursedesc);

            $coursesData[] = array(
                'course_id' => $course->courseid, 'course_name' => $course->coursename,
                'enrol' => $enrol, 'course_desc' => $courseDescription->value, 'views' => $view->visit,
                'teacherId' => $teacherId, 'teacherName' => $teacherName, 'price' => $price->value, 'image' => $overviewfiles,
                'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
            );
        }
    }

    return json_encode(["data" => $coursesData]);
}

function get_all_user_enrolled_courses($user)
{
    global $DB, $CFG;
    $courses = $DB->get_records_sql("SELECT co.id as courseId ,co.fullname as courseName,co.summary as courseDesc,co.category as catId,cat.name as catName FROM `mdl_course` as co join mdl_course_categories as cat ON co.category=cat.id ");
    $data_courses = array_values($courses);
    $coursesData = array();
    $coursecontacts = array();
    $teacherId = -1;
    $teacherName = "";

    foreach ($data_courses as $course) {
        $context = context_course::instance($course->courseid, MUST_EXIST);
        $enrol = is_enrolled($context, $user, '', true);

        if ($enrol) {
            $enrol = 'true';

            $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
            if (empty($price)) {
                $price = 'free';
            }

            $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
            $rating = array_values($rating);

            // $inCart = 'false';
            // $record = $DB->get_record('cart', array('user' => $user, 'course' => $course->courseid));
            // if (!empty($record)) {
            //    $inCart='true';
            // } 

            $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
            $imges = $DB->get_record("course", array('id' => $course->courseid));
            $courselist = new core_course_list_element($imges);

            $url = "";
            $overviewfiles = array();
            foreach ($courselist->get_course_overviewfiles() as $file) {
                $fileurl = moodle_url::make_webservice_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
                $overviewfiles[] = array(
                    'filename' => $file->get_filename(),
                    'fileurl' => $fileurl,
                    'filesize' => $file->get_filesize(),
                    'filepath' => $file->get_filepath(),
                    'mimetype' => $file->get_mimetype(),
                    'timemodified' => $file->get_timemodified(),
                );
            }
            $overviewfiles = $overviewfiles[0]['fileurl'];
            $teachers = $DB->get_records_sql("SELECT u.firstname AS name,u.lastname AS lastname, u.url as picture,u.description,u.email,ra.contextid,u.id As id, c.fullname as coursename
                FROM   mdl_course c
               LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
               LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
               LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id= '$course->courseid';");
            foreach ($teachers as $teacher) {
                $teacherId = $teacher->id;
                $teacherName = $teacher->name . ' ' . $teacher->lastname;
            }

            if (empty($teacherId)) {
                $teacherId = -1;
                $teacherName = null;
            }
            $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course->courseid));
            $course->coursedesc = edit_description($course->coursedesc);

            $coursesData[] = array(
                'course_id' => $course->courseid, 'course_name' => $course->coursename,
                'enrol' => $enrol, 'course_desc' => $courseDescription->value, 'views' => $view->visit,
                'teacherId' => $teacherId, 'teacherName' => $teacherName, 'price' => $price->value,
                'image' => $overviewfiles, 'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname, 'inCart' => $inCart
            );
        } else {
            $enrol = 'false';
        }
    }

    return json_encode(["data" => $coursesData]);
}

function get_course_badges($courseId)
{
    global $DB;
    $badges = $DB->get_records_sql('SELECT b.* FROM mdl_badge b WHERE b.courseid=' . $courseId . '');
    $context = context_course::instance($courseId, MUST_EXIST);

    return json_encode(['badges' => array_values($badges), 'context' => $context->id]);
}


function get_badge_users($badgeId)
{
    global $DB;
    $users = $DB->get_records_sql('SELECT u.* FROM `mdl_badge_issued` as bi JOIN `mdl_user` as u ON u.id=bi.`userid` WHERE `badgeid`=' . $badgeId . '');
    return json_encode(['users' => array_values($users)]);
}

function get_session($userid)
{
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid));
    $user = complete_user_login($user);
    if (!empty($user)) {
        $session = $DB->get_records_sql("SELECT sid  FROM `mdl_sessions` WHERE `userid`=" . $userid . " ORDER BY `timecreated` DESC LIMIT 1");
        $session = array_values($session);
        $item = $session[0];

        return json_encode(["session" => $item->sid]);
    }
}
// function get_session($userid)
// {
//     global $DB;
//     $user = $DB->get_record('user', array('id' => $userid));
//     $data='';
//     $session=$DB->get_records_sql("SELECT sid  FROM `mdl_sessions` WHERE `userid`=" . $userid . " ORDER BY `timecreated` DESC LIMIT 1");
//     if(!empty($session)){
//         foreach($session as $item){
//         $data=$item->sid;
//         }
//     }
//     else{
//         $user = complete_user_login($user);
//         if (!empty($user)) {
//             $session = $DB->get_records_sql("SELECT sid  FROM `mdl_sessions` WHERE `userid`=" . $userid . " ORDER BY `timecreated` DESC LIMIT 1");
//             $session = array_values($session);
//             $data = $session[0]->sid;   
//         }
//     }
//     return json_encode(["session" => $data]);
// }

function unenroll_user($courseid, $userid)
{
    global $DB;
    $instances = $DB->get_records('enrol', array('courseid' => $courseid));
    foreach ($instances as $instance) {
        $plugin = enrol_get_plugin($instance->enrol);
        $plugin->unenrol_user($instance, $userid);
    }
    return json_encode(["data" => 'done']);
}
function signUpNew($firstname, $lastname, $username, $email, $password, $phone = null, $phone2 = null, $role, $year = null, $city = null, $school = null, $center = null)
{
    global $DB;
    $userInfo = new stdClass();
    $yearInfo = new stdClass();
    $roleAssignment = new stdClass();
    $record = new stdClass();
    $check_userrname = $DB->get_record('user', array('username' => $username));
    $check_email = $DB->get_record('user', array('email' => $email));
    if (!empty($check_userrname)) {
        return json_encode(["message" => 'username exists']);
    } elseif (!empty($check_email)) {
        return json_encode(["message" => 'Email exists']);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return json_encode(["message" => 'invalid email']);
    } else {
        $userInfo->firstname = $firstname;
        $userInfo->lastname = $lastname;
        $userInfo->username = $username;
        $userInfo->email = $email;
        $hashPass = hash_internal_user_password($password);
        $userInfo->password = $hashPass;
        $userInfo->phone1 = $phone;
        $userInfo->phone2 = $phone2;
        $userInfo->confirmed = 1;
        $userInfo->mnethostid = 1;
        if ($role == 9) {
            $userInfo->id = $DB->insert_record('user', $userInfo);
        } elseif ($year != null && $role != 9) {
            if ($city != null) {
                $userInfo->city = $city;
            }
            $userInfo->country = "EG";
            $userInfo->lang = "ar";
            if ($school != null && $center != null) {
                $userInfo->id = $DB->insert_record('user', $userInfo);
                $yearMap = array("primary 1" => 1, "primary 2" => 2, "primary 3" => 3, "primary 4" => 4, "primary 5" => 5, "primary 6" => 6, "preparatory 1" => 7, "preparatory 2" => 8, "preparatory 3" => 9, "Secondary 1" => 10, "Secondary 2" => 11, "Secondary 3" => 12);
                $key = array_search(intval($year), $yearMap);
                $yearInfo->userid = $userInfo->id;
                $yearInfo->fieldid = 1;
                $yearInfo->data = $key;
                $yearInfo->dataformat = 0;
                $yearInfo->id = $DB->insert_record('user_info_data', $yearInfo);

                $optional_data = new stdClass();
                $optional_data->userid = $userInfo->id;
                $optional_data->school = $school;
                $optional_data->empty = $center;
                $optional_data->id = $DB->insert_record('optional_data_aibrahim', $optional_data);
            } else {
                return json_encode(["message" => 'you have to write your school and your center']);
            }
        } elseif ($year == null && $role != 9) {
            return json_encode(["message" => 'you have to add a year']);
        }
        $record->contextlevel = 30;
        $record->instanceid   =  $userInfo->id;
        $record->depth        = 0;
        $record->path         = null; //not known before insert
        $record->locked       = 0;
        $record->id = $DB->insert_record('context', $record);
        $parentpath = '/1';
        $record->path = $parentpath . '/' . $record->id;
        $record->depth = substr_count($record->path, '/');
        $DB->update_record('context', $record);
        $roleAssignment->roleid = $role;
        $roleAssignment->contextid = $record->id;
        $roleAssignment->userid = $userInfo->id;
        $roleAssignment->timemodified = time();
        $roleAssignment->modifierid = $userInfo->id;
        $roleAssignment->id = $DB->insert_record('role_assignments', $roleAssignment);
        return json_encode(["message" => 'success']);
    }
}
function get_all_courses($user, $teacher ,$lang='')
{
    global $DB, $CFG;
    $coursesData = array();
    $studentRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $isStudent = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $studentRole]);
    $teacherRole = $DB->get_field('role', 'id', array('shortname' => 'student'));
    $teacherRole = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $teacherRole]);
    $assisstantRole = $DB->get_field('role', 'id', array('shortname' => 'teacher'));
    $assisstantRole = $DB->record_exists('role_assignments', ['userid' => $user, 'roleid' => $assisstantRole]);
    if ($lang=="ar") {
        $lang = "and c.lang='ar'";
    } elseif($lang=="en") {
        $lang = "and c.lang='en'";
    }
    $courses = $DB->get_records_sql("SELECT  c.id AS courseId,c.visible as visible, c.fullname as courseName,c.category as catId,cat.name as catName ,cinfo.value as year
    FROM   mdl_course c
    LEFT OUTER JOIN mdl_customfield_data cinfo ON c.id=cinfo.instanceid

     LEFT OUTER JOIN mdl_course_categories  cat   ON c.category=cat.id 
      LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
    LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
     LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id 
     WHERE cx.contextlevel = '50' AND cinfo.fieldid=1 AND u.id= '$teacher' $lang;");
    $data_courses = array_values($courses);
    $teacherId = -1;
    $teacherName = "";
    $get_teacher_data = $DB->get_record('user', array('id' => $teacher));
    if ($isStudent || ($teacherRole && $teacher != $user)) {
        $student = $DB->get_record('user_info_data', array('userid' => $user, 'fieldid' => 1));
        $yearMap = array(1 => "primary 1", 2 => "primary 2", 3 => "primary 3", 4 => "primary 4", 5 => "primary 5", 6 => "primary 6", 7 => "preparatory 1", 8 => "preparatory 2", 9 => "preparatory 3", 10 => "Secondary 1", 11 => "Secondary 2", 12 => "Secondary 3");
        $key = array_search($student->data, $yearMap);
        foreach ($data_courses as $course) {

            if ($key == $course->year) {
                $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
                if (empty($price)) {
                    $price = 'free';
                }
                $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course->courseid));
                $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
                $rating = array_values($rating);

                // $inCart = 'false';
                // $record = $DB->get_record('cart', array('user' => $user, 'course' => $course->courseid));
                // if (!empty($record)) {
                //    $inCart='true';
                // } 

                $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
                $imges = $DB->get_record("course", array('id' => $course->courseid));
                $courselist = new core_course_list_element($imges);
                $context = context_course::instance($course->courseid, MUST_EXIST);
                $enrol = is_enrolled($context, $user, '', true);
                if ($enrol) {
                    $enrol = 'true';
                } else {
                    $enrol = 'false';
                }
                $overviewfiles = array();
                foreach ($courselist->get_course_overviewfiles() as $file) {
                    $fileurl = moodle_url::make_webservice_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    $overviewfiles[] = array(
                        'filename' => $file->get_filename(),
                        'fileurl' => $fileurl,
                        'filesize' => $file->get_filesize(),
                        'filepath' => $file->get_filepath(),
                        'mimetype' => $file->get_mimetype(),
                        'timemodified' => $file->get_timemodified(),
                    );
                }
                $overviewfiles = $overviewfiles[0]['fileurl'];


                $teacherId = $get_teacher_data->id;
                $teacherName = $get_teacher_data->firstname . ' ' . $get_teacher_data->lastname;

                $course->coursedesc = edit_description($course->coursedesc);
                if (empty($teacherId)) {
                    $teacherId = -1;
                    $teacherName = null;
                }
                // $coursesData[] = array('course_id' => $course->courseid, 'course_name' => $course->coursename,
                //  'enrol' => $enrol, 'course_desc' => $courseDescription->value,
                //   'views' => $view->visit, 'teacherId' => $teacherId,
                //    'teacherName' => $teacherName, 'price' => $price->value, 'image' => $url,
                //    'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
                //    );


                $coursesData[] = array(
                    'course_id' => $course->courseid, 'course_name' => $course->coursename,
                    'enrol' => $enrol, 'course_desc' => $courseDescription->value, 'course_year' => $course->year,
                    'views' => $view->visit, 'teacherId' => $teacherId,
                    'teacherName' => $teacherName, 'image' => $overviewfiles, 'price' => $price->value,
                    'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
                );
            }
        }
    } else {
        foreach ($data_courses as $course) {
            if ($course->visible) {
                $price = $DB->get_record('customfield_data', array('fieldid' => 12, 'instanceid' => $course->courseid));
                if (empty($price)) {
                    $price = 'free';
                }
                $courseDescription = $DB->get_record('customfield_data', array('fieldid' => 15, 'instanceid' => $course->courseid));
                $rating = $DB->get_records_sql("SELECT ceil(AVG(rate)) as rate FROM `mdl_course_rates` WHERE courseid=$course->courseid");
                $rating = array_values($rating);

                // $inCart = 'false';
                // $record = $DB->get_record('cart', array('user' => $user, 'course' => $course->courseid));
                // if (!empty($record)) {
                //    $inCart='true';
                // } 

                $view = $DB->get_record('course_views', array('courseid' => $course->courseid));
                $imges = $DB->get_record("course", array('id' => $course->courseid));
                $courselist = new core_course_list_element($imges);
                $context = context_course::instance($course->courseid, MUST_EXIST);
                $enrol = is_enrolled($context, $user, '', true);
                if ($enrol) {
                    $enrol = 'true';
                } else {
                    $enrol = 'false';
                }
                $url = "";
                // foreach ($courselist->get_course_overviewfiles() as $file) {
                //     $isimage = $file->is_valid_image();
                //     $url = file_encode_url("{$CFG->wwwroot}/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
                // }
                $overviewfiles = array();
                foreach ($courselist->get_course_overviewfiles() as $file) {
                    $fileurl = moodle_url::make_webservice_pluginfile_url(
                        $file->get_contextid(),
                        $file->get_component(),
                        $file->get_filearea(),
                        null,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false);
                    $overviewfiles[] = array(
                        'filename' => $file->get_filename(),
                        'fileurl' => $fileurl,
                        'filesize' => $file->get_filesize(),
                        'filepath' => $file->get_filepath(),
                        'mimetype' => $file->get_mimetype(),
                        'timemodified' => $file->get_timemodified(),
                    );
                }
                $overviewfiles = $overviewfiles[0]['fileurl'];
                $teachers = $DB->get_records_sql("SELECT u.firstname AS name,u.lastname AS lastname, u.url as picture,u.description,u.email,ra.contextid,u.id As id, c.fullname as coursename
                FROM   mdl_course c
               LEFT OUTER JOIN   mdl_context cx ON c.id = cx.instanceid
               LEFT OUTER JOIN   mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '3'
               LEFT OUTER JOIN   mdl_user u ON ra.userid = u.id WHERE cx.contextlevel = '50' AND c.id= '$course->courseid';");
                foreach ($teachers as $teacher) {
                    $teacherId = $teacher->id;
                    $teacherName = $teacher->name . ' ' . $teacher->lastname;
                }
                $course->coursedesc = edit_description($course->coursedesc);
                if (empty($teacherId)) {
                    $teacherId = -1;
                    $teacherName = null;
                }
                // $coursesData[] = array('course_id' => $course->courseid, 'course_name' => $course->coursename,
                //  'enrol' => $enrol, 'course_desc' => $courseDescription->value,
                //   'views' => $view->visit, 'teacherId' => $teacherId,
                //    'teacherName' => $teacherName, 'price' => $price->value, 'image' => $url,
                //    'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
                //    );


                $coursesData[] = array(
                    'course_id' => $course->courseid, 'course_name' => $course->coursename,
                    'enrol' => $enrol, 'course_desc' => $courseDescription->value, 'course_year' => $course->year,
                    'views' => $view->visit, 'teacherId' => $teacherId,
                    'teacherName' => $teacherName, 'image' => $overviewfiles, 'price' => $price->value,
                    'rate' => $rating[0]->rate, 'cat_id' => $course->catid, 'cat_name' => $course->catname
                );
            }
        }
    }

    return json_encode(["data" => $coursesData]);
}
function check_quiz_reviews($quiz)
{
    global $DB;
    $quizData = $DB->get_record('quiz', array('id' => $quiz));
    if ($quizData->reviewspecificfeedback == 0 && $quizData->reviewgeneralfeedback == 0 && $quizData->reviewgeneralfeedback == 0 && $quizData->reviewcorrectness == 0 && $quizData->reviewrightanswer == 0) {
        return json_encode(["data" => 'exam']);
    } else {
        return json_encode(["data" => 'quiz']);
    }
}
function user_language_update($userid, $lang)
{
    global $DB;
    $user = $DB->get_record('user', array('id' => $userid));
    $record = new stdClass();
    $record->id = $user->id;
    $record->lang = $lang;
    $record->id = $DB->update_record('user', $record);
}
function get_members_array($groupID)
{
    global $DB;
    $members = array();
    /*
    SELECT $fields
                                   FROM {user} u
                                     INNER JOIN {groups_members} gm ON u.id = gm.userid
                                     INNER JOIN {groupings_groups} gg ON gm.groupid = gg.groupid
                                  WHERE  gg.groupingid = ?
                               ORDER BY $sort", array($groupingid));
    */

    $members = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as fullname,u.url as image,gm.timeadded as added,op.empty as center_name ,op.school as school_name
  FROM mdl_user u
  INNER join mdl_optional_data_aibrahim as op ON op.userid=u.id

    INNER JOIN mdl_groups_members gm ON u.id = gm.userid WHERE  gm.groupid =   $groupID ORDER BY u.id ASC");
    $members = array_values($members);
    foreach ($members as $member) {
        $member->added = date('m/d/Y H:i:s', $member->added);
    }
    return $members;
}
function get_enrolled_users_members($courseid, $option)
{
    global $DB;
    $record = $DB->get_record('groups', array('courseid' => $courseid, "name" => "Enrolled Users"));
    $group_members = get_members_array($record->id);
    $notmember = array();
    $member = array();
    $notmemberData = array();
    $memberData = array();
    $enrolled_students = $DB->get_records_sql("SELECT u.id As id,concat(u.firstname , ' ', u.lastname)as fullname,u.url as image,op.empty as center_name ,op.school as school_name
    FROM mdl_course c LEFT OUTER JOIN mdl_context cx ON c.id = cx.instanceid 
    LEFT OUTER JOIN mdl_role_assignments ra ON cx.id = ra.contextid AND ra.roleid = '5'
     LEFT OUTER JOIN mdl_user u ON ra.userid = u.id 
     INNER join mdl_optional_data_aibrahim as op ON op.userid=u.id

     WHERE cx.contextlevel = '50' AND c.id=$courseid");
    foreach ($enrolled_students as $en_s) {
        $flag = 0;
        foreach ($group_members as $gm) {
            if ($en_s->id == $gm->id) {
                $flag = 1;
            }
        }
        if ($flag == 0) {
            array_push($notmember, $en_s);
        }
    }
    if ($option == "Desc") {
        usort($group_members, function ($a, $b) {
            return strtolower($a->added) < strtolower($b->added);
        });
    }
    if ($option == "ASC") {
        usort($group_members, function ($a, $b) {
            return strtolower($a->added) > strtolower($b->added);
        });
    }

    if ($option == "atoz" || $option == " ") {
        usort($group_members, function ($a, $b) {
            return strtolower($a->fullname) > strtolower($b->fullname);
        });
        usort($notmember, function ($a, $b) {
            return strtolower($a->fullname) > strtolower($b->fullname);
        });
    }
    if ($option == "ztoa") {

        usort($group_members, function ($a, $b) {
            return strtolower($a->fullname) < strtolower($b->fullname);
        });
        usort($notmember, function ($a, $b) {
            return strtolower($a->fullname) < strtolower($b->fullname);
        });
    }

    return json_encode(['groupmember' => $group_members, 'others' => $notmember]);
}
function check_device_code($userid, $code, $activityid, $device, $deviceid = null)
{
    global $DB;
    $check_code = $DB->get_record('codes_generator', array('code' => $code));
    $code_used = $DB->get_record('activity_restrict_views_code_device_check', array('code' => $check_code->id));
    if (!empty($check_code)) {
        $check_views = $DB->get_record('activity_restrict_views_code_device_check', array('expired' => 0, 'code' => $check_code->id));
        $check_code_expiration = $DB->get_record('activity_restrict_views_code_device_check', array('expired' => 1, 'code' => $check_code->id));
        // $code_used=$DB->get_record('activity_restrict_views_code_device_check',array('code'=>$check_code->id,'userid'=>$userid,));
    } else {
        $err = "wrong-code";
        return json_encode(['data' => $err]);
    }
    if (!empty($check_code_expiration)) {
        $err = "expired-code";
        return json_encode(['data' => $err]);
    }
    if (!empty($check_views)) {
        if ($check_views->userid != $userid) {
            $err = "wrong-code";
            return json_encode(['data' => $err]);
        }
    }

    if ($device == "1") {
        // $check_views=$DB->get_record('activity_restrict_views_code_device_check',array('userid'=>$userid,'expired'=>0,'code'=>$check_code->id));

        if (empty($check_views)) {
            $ins = new stdClass();
            $ins->code = $check_code->id;
            $ins->number_of_views = 0;
            $ins->userid = $userid; //student id
            $ins->activityid = $activityid;
            $ins->expired = 0;
            $ins->web = 1;
            $ins->mobile = 0;
            $ins->col1 = "";
            $ins->id = $DB->insert_record('activity_restrict_views_code_device_check', $ins);
            if (empty($_COOKIE['' . $check_code->code . ''])) {
                setcookie('' . $check_code->code . '', $check_code->code, time() + 60 * 60 * $check_code->last);
                $data = new stdClass();
                $data->id = $ins->id;
                $data->number_of_views = 1;
                $data->id = $DB->update_record('activity_restrict_views_code_device_check', $data);
            }
            return json_encode(['data' => "done"]);
        } elseif (!empty($check_views) && $check_views->mobile != "1") {
            if ($check_code->number_of_tries == $check_views->number_of_views) {
                $ins = new stdClass();
                $ins->id = $check_views->id;
                $ins->expired = 1;
                $ins->id = $DB->update_record('activity_restrict_views_code_device_check', $ins);
                $err = "Expired";
                return json_encode(['data' => $err]);

                // redirect("list.php");

            } elseif (!empty($check_views) && $activityid == $check_views->activityid) {
                if (!empty($_COOKIE['' . $check_code->code . '']) && $_COOKIE['' . $check_code->code . ''] == $check_code->code) {
                    $ins = new stdClass();
                    $ins->id = $check_views->id;
                    $ins->number_of_views = $check_views->number_of_views + 1;
                    $ins->id = $DB->update_record('activity_restrict_views_code_device_check', $ins);
                    $data = "true";
                    return json_encode(['data' => $data]);
                } else {
                    $err = "error";
                    return json_encode(['data' => $err]);
                }
            } elseif (!empty($check_views) && $activityid != $check_views->activityid) {
                $err = "refused";
                return json_encode(['data' => $err]);
            }
        } else {
            $err = "You have to enter from your device";
            return json_encode(['data' => $err]);
        }
    } elseif ($device == "2") {
        if (empty($check_views)) {
            $ins = new stdClass();
            $ins->code = $check_code->id;
            $ins->number_of_views = 1;
            $ins->userid = $userid; //student id
            $ins->activityid = $activityid;
            $ins->expired = 0;
            $ins->web = 0;
            $ins->mobile = 1;
            $ins->col1 = $deviceid;
            $ins->id = $DB->insert_record('activity_restrict_views_code_device_check', $ins);
            return json_encode(['data' => "true"]);
        } elseif (!empty($check_views) && $check_views->web != "1" && $check_views->mobile == "1") {
            if ($check_code->number_of_tries == $check_views->number_of_views) {
                $ins = new stdClass();
                $ins->id = $check_views->id;
                $ins->expired = 1;
                $ins->id = $DB->update_record('activity_restrict_views_code_device_check', $ins);
                $err = "Expired";
                return json_encode(['data' => $err]);

                // redirect("list.php");

            } elseif (!empty($check_views) && $activityid != $check_views->activityid || $check_views->col1 != $deviceid) {
                $err = "refused";
                return json_encode(['data' => $err]);
            } elseif (!empty($check_views) && $activityid == $check_views->activityid) {

                $ins = new stdClass();
                $ins->id = $check_views->id;
                $ins->number_of_views = $check_views->number_of_views + 1;
                $ins->id = $DB->update_record('activity_restrict_views_code_device_check', $ins);
                $data = "true";
                return json_encode(['data' => $data]);
            }
        } else {
            $err = "You have to enter from your device";
            return json_encode(['data' => $err]);
        }
    }
}
function check_availability($courseid, $cmid, $userid)
{
    global $DB, $CFG;
    require_once($CFG->dirroot . "/course/lib.php");

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    // return json_encode(['data' => $course]);
    $modinfo = get_fast_modinfo($course, $userid);
    $cm = $modinfo->get_cm($cmid);
    if ($cm->uservisible) {
        // User can access the activity.
        return json_encode(['flag' => true,'data'=>""]);
    } else if ($cm->availableinfo) {
        // User cannot access the activity, but on the course page they will
        // see a link to it, greyed-out, with information (HTML format) from
        // $cm->availableinfo about why they can't access it.
        $reason1 = json_encode($cm->availableinfo);
        $reason = json_decode($reason1);
        $string = '';
        if (gettype($reason) == 'string') {

            $string = strip_tags($cm->availableinfo);
        } else {
            for ($i = 0; $i < count($reason->items); $i++) {
                // array_push($module['avail_message'], $reason->items[$i]->text." <br>");
                $string .= strip_tags($reason->items[$i]) . ",";
            }
            $string = substr($string, 0, -1);

            // $module['avail_message'] = $string;
        }
        return json_encode(['flag' => false,'data' =>   $string]);

    } else {
        // User cannot access the activity and they will not see it at all.
        return json_encode(['data' => false, 'data'=>"User cannot access the activity and they will not see it at all"]);
    }
}
