<?php
global $CFG;
require_once($CFG->dirroot . '/theme/edumy/ccn/block_handler/ccn_block_handler.php');
class block_cocoon_services extends block_base
{

  public function init()
  {
    $this->title = get_string('pluginname', 'block_cocoon_services');
  }

  public function specialization()
  {
    global $CFG, $DB;
    include($CFG->dirroot . '/theme/edumy/ccn/block_handler/specialization.php');
    if (empty($this->config)) {
      $this->config = new \stdClass();
      $this->config->slidesnumber = '3';
      $this->config->title = 'Why Choose Us';
      $this->config->subtitle = 'Cum doctus civibus efficiantur in imperdiet deterruisset.';
      $this->config->title1 = 'Trusted by Thousands';
      $this->config->title2 = 'Premium Memberships';
      $this->config->title3 = 'Qualified Instructors';
      $this->config->body1 = 'Aliquam dictum elit vitae mauris facilisis at dictum urna dignissim donec vel lectus vel felis.';
      $this->config->body2 = 'Aliquam dictum elit vitae mauris facilisis at dictum urna dignissim donec vel lectus vel felis.';
      $this->config->body3 = 'Aliquam dictum elit vitae mauris facilisis at dictum urna dignissim donec vel lectus vel felis.';
      $this->config->icon1 = 'flaticon-student-3';
      $this->config->icon2 = 'flaticon-first';
      $this->config->icon3 = 'flaticon-employee';
    }
  }
  public function get_content()
  {
    global $CFG, $DB;
    require_once($CFG->libdir . '/filelib.php');
    if ($this->content !== null) {
      return $this->content;
    }
    $this->content         =  new stdClass;
    if (!empty($this->config->title)) {
      $this->content->title = $this->config->title;
    } else {
      $this->content->title = '';
    }
    if (!empty($this->config->subtitle)) {
      $this->content->subtitle = $this->config->subtitle;
    } else {
      $this->content->subtitle = '';
    }

    if ($this->config->style == 1) {
      $this->content->style = '';
    } else {
      $this->content->style = 'style2';
    }

    if (!empty($this->config) && is_object($this->config)) {
      $data = $this->config;
      $data->slidesnumber = is_numeric($data->slidesnumber) ? (int)$data->slidesnumber : 3;
    } else {
      $data = new stdClass();
      $data->slidesnumber = '3';
    }

    $fs = get_file_storage();
    $files = $fs->get_area_files($this->context->id, 'block_cocoon_services', 'content');
    $this->content->image = '';
    foreach ($files as $file) {
      $filename = $file->get_filename();
      if ($filename <> '.') {
        $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), null, $file->get_filepath(), $filename);
        $this->content->image .=  $url;
      }
    }

    echo '<style>';
    echo '
    .name_img { 
      margin-top: 10vh;
      width: 100px;
      scale: 4;
    }
    
    .why_chose_us_v {
      background-color: #fff0;
      border-radius: 8px;
      margin-bottom: 30px;
      padding: 20px 30px;
      position: relative;
      text-align: center;
      -webkit-transition: all 0.3s ease;
      -moz-transition: all 0.3s ease;
      -o-transition: all 0.3s ease;
      transition: all 0.3s ease;
    }
    .flip-card {
      background-color: transparent;
      width: 100%;
      height: 500px;
      perspective: 1000px;
    }
    
    .flip-card-inner {
      position: relative;
      width: 100%;
      height: 100%;
      text-align: center;
      transition: transform 0.6s;
      transform-style: preserve-3d;
      box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
      border-radius: 15px;
    }
    
    .flip-card:hover .flip-card-inner {
      scale: 1.01;
      transform: rotateY(180deg);
    }
    
    .flip-card-front, .flip-card-back {
      position: absolute;
      width: 100%;
      height: 100%;
      -webkit-backface-visibility: hidden;
      backface-visibility: hidden;
      border-radius: 15px;
      color: white;
      background-image: linear-gradient(#0E2647, #B21F60);
    }
    
    .flip-card-front {
      display: flex;
      flex-direction: column ;
      justify-content: center;
    }

    .col-xl-4 {
      margin-top: 35px;
    }

    .flip-card-front .icon_img { 
      width: 100%;
      height: 250px;
      margin-bottom: 100px;
    }
    
    .flip-card-back {
      padding: 20px 20px;
      transform: rotateY(180deg);
    }
    .flip-card-back p {
      font-size: 20px;
    }

    #card_2 {margin-top: -50px;}

    #card_3 .flip-card-front {
      display: flex;
      flex-direction: column ;
      justify-content: center;
    }
    ';
    echo '</style>';


    $this->content->text = '
    <section class="whychose_us">
  		<div class="container">
  			<div class="row">
  				<div class="col-lg-6 offset-lg-3">
  					<div class="main-title text-center wow fadeInDown" data-wow-delay="0.2s">
             <img class="name_img" src="https://academy2022.nitg-eg.com/services_imgs/name.webp" alt="Name">
  					</div>
  				</div>
  			</div>
  			<div class="row card_fd">';

    if ($data->slidesnumber > 0) {
      $font = 'Jomhuria';

      for ($i = 1; $i <= $data->slidesnumber; $i++) {
        $title = 'title' . $i;
        $link = 'link' . $i;
        $icon = 'icon' . $i;
        $body = 'body' . $i;

        $this->content->text .= '
        <div class="col-md-6 col-lg-4 col-xl-4">
        <div class="why_chose_us_v" style"padding: 0px;background-color: #fff0;">';
        if (!empty($data->$link)) {
          $this->content->text .= '<a href="' . $data->$link . '">';
        }

        $this->content->text .= '
        <div class="flip-card wow bounceInUp" id="card_' . $i . '">
          <div class="flip-card-inner">
        		<div class="flip-card-front">
            <div class="icon_img">
              <img style="width: 250px;" img src="https://academy2022.nitg-eg.com/services_imgs/img(' . $i . ').webp" alt="Avatar">
            </div>
            <h1 style="color: white;font-family: ' . $font . ';" data-ccn="title' . $i . '">' . format_text($data->$title, FORMAT_HTML, array('filter' => true)) . '</h1>
        		</div>
        		<div class="flip-card-back">
            <div class="icon_img">
              <img style="width: 150px;" img src="https://academy2022.nitg-eg.com/services_imgs/img(' . $i . ').webp" alt="Avatar">
            </div>
            <h1 style="margin-bottom: 30px;color: white;font-family: ' . $font . ';" data-ccn="title' . $i . '">' . format_text($data->$title, FORMAT_HTML, array('filter' => true)) . '</h1>
        			<p data-ccn="body' . $i . '">' . format_text($data->$body, FORMAT_HTML, array('filter' => true)) . '</p>
        		</div>
          </div>
        </div>';
        if (!empty($data->$link)) {
          $this->content->text .= '</a>';
        }
        $this->content->text .= '
			</div>
      </div>


';
      }
    }
    $this->content->text .= '

  			</div>
  		</div>
  	</section>
';
    return $this->content;
  }

  /**
   * Allow multiple instances in a single course?
   *
   * @return bool True if multiple instances are allowed, false otherwise.
   */
  public function instance_allow_multiple()
  {
    return true;
  }

  /**
   * Enables global configuration of the block in settings.php.
   *
   * @return bool True if the global configuration is enabled.
   */
  function has_config()
  {
    return true;
  }

  /**
   * Sets the applicable formats for the block.
   *
   * @return string[] Array of pages and permissions.
   */
  function applicable_formats()
  {
    $ccnBlockHandler = new ccnBlockHandler();
    return $ccnBlockHandler->ccnGetBlockApplicability(array('all'));
  }

  public function html_attributes()
  {
    global $CFG;
    $attributes = parent::html_attributes();
    include($CFG->dirroot . '/theme/edumy/ccn/block_handler/attributes.php');
    return $attributes;
  }
}
