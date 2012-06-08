<?php
/*
	活动
设计成一种作业类型（assignment type）	done
可基于offline assignment type开发		done
	学生界面
只能查看自己得分情况							done
密码验证窗口，连续三次输入错误就ban一个小时		done
显示得分历史									done
	教师界面
教师可以查看所有人得分情况		create a student account
可以手工打分					done
生成、打印成绩卡页面			done
单张纸上打印多张成绩卡			done
	成绩卡
密码唯一，与对应活动绑定，只能生效一次，有有效期	done
成绩卡上除了密码，还要有课程名、活动名、有效期		乱码,why?
*/

require_once($CFG->libdir.'/formslib.php');

class assignment_pointcard extends assignment_base {

    function assignment_pointcard($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'pointcard';
    }
	
	function view() {

        $context = get_context_instance(CONTEXT_MODULE,$this->cm->id);
        require_capability('mod/assignment:view', $context);

        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}",
               $this->assignment->id, $this->cm->id);

        $this->view_header();

        $this->view_intro();

		if(has_capability('mod/assignment:grade', $context)){
			$this->view_teacher();
		} else{
			$this->view_student();
		}
		
        $this->view_dates();

        $this->view_feedback();

        $this->view_footer();
    }
	
	/*老师的显示界面*/
	function view_teacher(){
		global $OUTPUT,$USER,$PAGE;
		
		echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
		$mform = new mod_assignment_pointcard_teacher_form($PAGE->url->out());
		$mform->display();
		echo $OUTPUT->box_end();
		if (!$mform->is_cancelled() && $data = $mform->get_submitted_data()){ // 如果表单定义含有取消按钮，点击取消按钮也算是提交表单，使用此方法判断			
			$this->update_submission_teacher($USER->id,$data);
		}

	}
	
	/*处理老师提交的数据*/
	function update_submission_teacher($userid, $data) {
        global $DB,$COURSE;
        		
		//以记分卡数目作为循环控制
		srand($this->seed());			//重置种子
		for($i=0;$i<$data->num;$i++){

			$rd = rand(1000000000,9999999999);	//生成10位随机数
		
			//写入数据库
			$update = new stdClass();
		
			$update->assignment = $this->assignment->id;
			$update->assignment_name = $this->assignment->name;
			$update->password = $rd;
			$update->grade = $data->grade;
			$update->timecreated = time();

			
			if($DB->insert_record('assignment_pointcards', $update)){
				print_r("<table>");
				print_r("<tr>");
				/*课程名*/
				print_r("<td>".$this->course->fullname."</td>");
				/*作业名*/
				print_r("<td>".$this->assignment->name."</td>");
				print_r("<td>".$rd."</td>");
				print_r("<td>".get_string('duedate','assignment')."</td>");
				print_r("<td>".date('Y-m-j G:i:s',$this->assignment->timedue)."</td>");
				echo "<br />";
			}else{
				$i=$i-1;
			}
		}
	}
	
	/*设置种子*/
	function seed(){
		list($msec, $sec) = explode(' ', microtime());
		return (float) $sec;
	}
	
	/*学生的显示界面*/
	function view_student(){
		global $OUTPUT,$PAGE,$USER,$DB;
		
		/*打印成绩历史记录*/
		$sql = 'SELECT * 
				FROM {assignment_pointcards} as a
				WHERE a.userid = ?';
		$param = array($USER->id);		
		$grades = $DB->get_records_sql($sql,$param);
						
		print_r("<table>");
		print_r("<tr>");
		print_r("<td>".get_string('history_assignment','assignment_pointcard')."</td>"); 
		print_r("<td>".get_string('history_point','assignment_pointcard')."</td>"); 
		print_r("<td>".get_string('history_time','assignment_pointcard')."</td>");
		print_r("<tr>");
		foreach($grades as $v1){ 
			print_r("<tr>"); 
			print_r("<td>".$v1->assignment_name."</td>"); 
			print_r("<td>".$v1->grade."</td>"); 
			print_r("<td>".date("Y-m-d",$v1->timeassociated)."</td>"); 
			print_r("</tr>");
		}
		print_r("</table>");
		
		echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
		$mform = new mod_assignment_pointcard_student_form($PAGE->url->out());
		$mform->display();
		if (!$mform->is_cancelled() && $data = $mform->get_submitted_data()){ // 如果表单定义含有取消按钮，点击取消按钮也算是提交表单，使用此方法判断			
			$this->update_submission_student($USER->id,$data);
		}
		echo $OUTPUT->box_end();
	}
	
	/*处理学生提交的数据*/
	function update_submission_student($userid, $data){
		global $DB,$USER;
		
		if($timesfalse = $DB->get_record('timesfalse',array('userid'=>$userid))){		
			if($timesfalse->banmark == 1 && (time() - $timesfalse->bantime) <= 3600){
				$timeleft = 60 - (time() - $timesfalse->bantime)/60;
				echo '<p style="color:red">'.get_string('student_banned','assignment_pointcard',floor($timeleft)).'</p>';
				return;
			}
			else if($timesfalse->banmark == 1 && (time() - $timesfalse->bantime) > 3600){
				$update1 = new stdClass();
				$update1->id = $timesfalse->id;
				$update1->userid = $userid;
				$update1->banmark = 0;
				$update1->timesleft = 3;
				$DB->update_record('timesfalse', $update1);
			}
		}else{
			$update2 = new stdClass();
			$update2->userid = $userid;
			$DB->insert_record('timesfalse', $update2);
			$timesfalse = $DB->get_record('timesfalse',array('userid'=>$userid));
		}
		
		/*数据库判断的条件为1、密码相同，2、之前未被使用过*/
		if($r = $DB->get_record('assignment_pointcards',array('password'=>$data->password,'timeassociated'=>NULL))){
			$submission = $this->get_submission($userid, true);
			$submission->grade = $submission->grade + $r->grade;
			$this->update_grade($submission);
			
			$update3 = new stdClass();
			$update3->id = $r->id;
			$update3->userid = $USER->id;
			$update3->timeassociated = time();
			$DB->update_record('assignment_pointcards', $update3);
			
			echo '<p style="color:red">'.get_string('student_success','assignment_pointcard',$r->grade).'</p>';
							
		/*密码重复*/
		}else if($r = $DB->get_record('assignment_pointcards',array('password'=>$data->password))){
			echo '<p style="color:red">'.get_string('student_repeated','assignment_pointcard').'</p>';
		}else{								//密码错误
			if($timesfalse->timesleft > 1){			
				$update4 = new stdClass();
				$update4->id = $timesfalse->id;
				$update4->timesleft = $timesfalse->timesleft - 1;
				$DB->update_record('timesfalse', $update4);
				
				echo '<p style="color:red">'.get_string('student_false','assignment_pointcard',($timesfalse->timesleft-1)).'</p>';
			}else{
				$update5 = new stdClass();
				$update5->id = $timesfalse->id;
				$update5->banmark = 1;
				$update5->bantime = time();
				$DB->update_record('timesfalse', $update5);
				
				$timeleft = 60 - (time() - $timesfalse->bantime)/60;
				echo '<p style="color:red">'.get_string('student_banned','assignment_pointcard',floor($timeleft)).'</p>';
			}
		}
	}
}
	
class mod_assignment_pointcard_student_form extends moodleform {
    function definition() {
        $mform = &$this->_form;

        //类型是text
		$mform->addElement('text', 'password', get_string('password_summit', 'assignment_pointcard'));
        $mform->setType('password', PARAM_INT);
        $mform->addRule('password', null, 'required', null, 'client');
        $this->add_action_buttons(); // 默认添加一个保存按钮和一个取消按钮
    }
}

class mod_assignment_pointcard_teacher_form extends moodleform{
	function definition(){
		$mform = &$this->_form;
		
		//输入分数卡数量，限定为int
		$mform->addElement('text', 'num', get_string('pointcard_num','assignment_pointcard'));
        $mform->setType('num', PARAM_INT);
        $mform->addRule('num', null, 'required', null, 'client');

		//输入分数卡分值，限定为int
		$mform->addElement('text', 'grade', get_string('pointcard_grade','assignment_pointcard'));
        $mform->setType('grade', PARAM_INT);
        $mform->addRule('grade', null, 'required', null, 'client');		
		$this->add_action_buttons();
	}
}

