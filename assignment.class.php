<?php
/*
	�
��Ƴ�һ����ҵ���ͣ�assignment type��	done
�ɻ���offline assignment type����		done
	ѧ������
ֻ�ܲ鿴�Լ��÷����							done
������֤���ڣ�����������������banһ��Сʱ		done
��ʾ�÷���ʷ									done
	��ʦ����
��ʦ���Բ鿴�����˵÷����		create a student account
�����ֹ����					done
���ɡ���ӡ�ɼ���ҳ��			done
����ֽ�ϴ�ӡ���ųɼ���			done
	�ɼ���
����Ψһ�����Ӧ��󶨣�ֻ����Чһ�Σ�����Ч��	done
�ɼ����ϳ������룬��Ҫ�пγ������������Ч��		����,why?
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
	
	/*��ʦ����ʾ����*/
	function view_teacher(){
		global $OUTPUT,$USER,$PAGE;
		
		echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
		$mform = new mod_assignment_pointcard_teacher_form($PAGE->url->out());
		$mform->display();
		echo $OUTPUT->box_end();
		if (!$mform->is_cancelled() && $data = $mform->get_submitted_data()){ // ��������庬��ȡ����ť�����ȡ����ťҲ�����ύ����ʹ�ô˷����ж�			
			$this->update_submission_teacher($USER->id,$data);
		}

	}
	
	/*������ʦ�ύ������*/
	function update_submission_teacher($userid, $data) {
        global $DB,$COURSE;
        		
		//�ԼǷֿ���Ŀ��Ϊѭ������
		srand($this->seed());			//��������
		for($i=0;$i<$data->num;$i++){

			$rd = rand(1000000000,9999999999);	//����10λ�����
		
			//д�����ݿ�
			$update = new stdClass();
		
			$update->assignment = $this->assignment->id;
			$update->assignment_name = $this->assignment->name;
			$update->password = $rd;
			$update->grade = $data->grade;
			$update->timecreated = time();

			
			if($DB->insert_record('assignment_pointcards', $update)){
				print_r("<table>");
				print_r("<tr>");
				/*�γ���*/
				print_r("<td>".$this->course->fullname."</td>");
				/*��ҵ��*/
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
	
	/*��������*/
	function seed(){
		list($msec, $sec) = explode(' ', microtime());
		return (float) $sec;
	}
	
	/*ѧ������ʾ����*/
	function view_student(){
		global $OUTPUT,$PAGE,$USER,$DB;
		
		/*��ӡ�ɼ���ʷ��¼*/
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
		if (!$mform->is_cancelled() && $data = $mform->get_submitted_data()){ // ��������庬��ȡ����ť�����ȡ����ťҲ�����ύ����ʹ�ô˷����ж�			
			$this->update_submission_student($USER->id,$data);
		}
		echo $OUTPUT->box_end();
	}
	
	/*����ѧ���ύ������*/
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
		
		/*���ݿ��жϵ�����Ϊ1��������ͬ��2��֮ǰδ��ʹ�ù�*/
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
							
		/*�����ظ�*/
		}else if($r = $DB->get_record('assignment_pointcards',array('password'=>$data->password))){
			echo '<p style="color:red">'.get_string('student_repeated','assignment_pointcard').'</p>';
		}else{								//�������
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

        //������text
		$mform->addElement('text', 'password', get_string('password_summit', 'assignment_pointcard'));
        $mform->setType('password', PARAM_INT);
        $mform->addRule('password', null, 'required', null, 'client');
        $this->add_action_buttons(); // Ĭ�����һ�����水ť��һ��ȡ����ť
    }
}

class mod_assignment_pointcard_teacher_form extends moodleform{
	function definition(){
		$mform = &$this->_form;
		
		//����������������޶�Ϊint
		$mform->addElement('text', 'num', get_string('pointcard_num','assignment_pointcard'));
        $mform->setType('num', PARAM_INT);
        $mform->addRule('num', null, 'required', null, 'client');

		//�����������ֵ���޶�Ϊint
		$mform->addElement('text', 'grade', get_string('pointcard_grade','assignment_pointcard'));
        $mform->setType('grade', PARAM_INT);
        $mform->addRule('grade', null, 'required', null, 'client');		
		$this->add_action_buttons();
	}
}

