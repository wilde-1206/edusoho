<?php
namespace Topxia\Service\Notification;

use Topxia\Api\Util\MobileSchoolUtil;
use Topxia\Service\Common\ServiceEvent;
use Topxia\Service\Common\ServiceKernel;
use Topxia\Service\CloudPlatform\CloudAPIFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PushMessageEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'testpaper.reviewed'        => 'onTestPaperReviewed',
            'course.lesson.publish'     => 'onLessonPublish',
            'course.join'               => 'onCourseJoin',
            'course.quit'               => 'onCourseQuit',
            'course.create'             => 'onCourseCreate',
            'course.publish'            => 'onCoursePublish',
            'course.lesson.delete'      => 'onCourseLessonDelete',
            'course.lesson.update'      => 'onCourseLessonUpdate',
            'announcement.create'       => 'onAnnouncementCreate',
            'classroom.create'          => 'onClassroomCreate',
            'classroom.join'            => 'onClassroomJoin',
            'classroom.quit'            => 'onClassroomQuit',
            'classroom.put_course'      => 'onClassroomPutCourse',
            'article.create'            => 'onArticleCreate',
            'course.thread.post.create' => 'onCourseThreadPostCreate',
            'homework.check'            => 'onHomeworkCheck',
            'course.lesson_finish'      => 'onCourseLessonFinish',
            'course.lesson_start'       => 'onCourseLessonStart',
            'course.thread.create'      => 'onCourseThreadCreate',
            'user.update'               => 'onUserUpdate',
            'course.lesson.create'      => 'onCourseLessonCreate',
            'profile.update'            => 'onProfileUpdate',
            'course.update'             => 'onCourseUpdate',

            'user.register'             => 'pushCloudData',
            'mobile.change'             => 'pushCloudData',
            'course.close'              => 'pushCloudData',
            'course.delete'             => 'pushCloudData',
            'course.lesson.unpublish'   => 'pushCloudData',
            'article.update'            => 'pushCloudData',
            'article.trash'             => 'pushCloudData',
            'article.delete'            => 'pushCloudData',
            'thread.update'             => 'pushCloudData',
            'thread.delete'             => 'pushCloudData',
            'thread.create'             => 'pushCloudData',
            'course.thread.update'      => 'pushCloudData',
            'course.thread.delete'      => 'pushCloudData',
            'user.change_nickname'      => 'pushCloudData',
            'user.change_email'         => 'pushCloudData',
            'user.unlock'               => 'pushCloudData',
            'user.lock'                 => 'pushCloudData'

        );
    }

    public function onCourseCreate(ServiceEvent $event)
    {
        $course = $event->getSubject();

        $currentUser = ServiceKernel::instance()->getCurrentUser();
        $message     = array(
            'name'    => $course['title'],
            'clients' => array(array(
                'clientId'   => $currentUser['id'],
                'clientName' => $currentUser['nickname']
            ))
        );

        $result = CloudAPIFactory::create('root')->post('/im/me/conversation', $message);
        $course = $this->getCourseService()->updateCourse($course['id'], array('conversationId' => $result['no']));

        $this->getCloudDataService()->push('edusoho.course.create', $course, time());
    }

    public function onClassroomCreate(ServiceEvent $event)
    {
        $classroom = $event->getSubject();

        $currentUser = ServiceKernel::instance()->getCurrentUser();
        $message     = array(
            'name'    => $classroom['title'],
            'clients' => array(array('clientId' => $currentUser['id'], 'clientName' => $currentUser['nickname']))
        );

        $result = CloudAPIFactory::create('root')->post('/im/me/conversation', $message);
        $this->getClassroomService()->updateClassroom($classroom['id'], array('conversationId' => $result['no']));
    }

    public function onTestPaperReviewed(ServiceEvent $event)
    {
        $testpaper = $event->getSubject();
        $result    = $event->getArgument('testpaperResult');

        $testpaper['target']       = explode('-', $testpaper['target']);
        $testpaperResult['target'] = explode('-', $result['target']);
        $lesson                    = $this->getCourseService()->getLesson($testpaperResult['target'][2]);
        $target                    = $this->getTarget($testpaper['target'][0], $testpaper['target'][1]);

        $from = array(
            'type'  => $target['type'],
            'id'    => $target['id'],
            'image' => $target['image']
        );

        $to = array('type' => 'user', 'id' => $result['userId']);

        $body = array(
            'type'     => 'testpaper.reviewed',
            'id'       => $result['id'],
            'lessonId' => $lesson['id']
        );

        $this->getCloudDataService()->push('edusoho.testpaper.reviewed', $testpaper, time());
        //$this->push($lesson['title'], $result['paperName'], $from, $to, $body);
    }

    public function onLessonPublish(ServiceEvent $event)
    {
        $lesson = $event->getSubject();
        $course = $this->getCourseService()->getCourse($lesson['courseId']);
        $from   = array(
            'type'  => 'course',
            'id'    => $course['id'],
            'image' => $this->getFileUrl($course['smallPicture'])
        );

        $to = array('type' => 'course', 'id' => $course['id']);

        $body = array('type' => 'lesson.publish', 'id' => $lesson['id'], 'lessonType' => $lesson['type']);

        $this->push($course['title'], $lesson['title'], $from, $to, $body);

        $mobileSetting = $this->getSettingService()->get('mobile');

        if ((!isset($mobileSetting['enable']) || $mobileSetting['enable']) && $lesson['type'] == 'live') {
            $this->createJob($lesson);
        }

        $this->getCloudDataService()->push('edusoho.lesson.publish', $lesson, time());
    }

    public function onCourseLessonUpdate(ServiceEvent $event)
    {
        $context       = $event->getSubject();
        $argument      = $context['argument'];
        $lesson        = $context['lesson'];
        $mobileSetting = $this->getSettingService()->get('mobile');

        if ($lesson['type'] == 'live' && isset($argument['startTime']) && $argument['startTime'] != $lesson['fields']['startTime'] && (!isset($mobileSetting['enable']) || $mobileSetting['enable'])) {
            $jobs = $this->getCrontabService()->findJobByTargetTypeAndTargetId('lesson', $lesson['id']);

            if ($jobs) {
                $this->deleteJob($jobs);
            }

            if ($lesson['status'] == 'published') {
                $this->createJob($lesson);
            }
        }

        $this->getCloudDataService()->push('eduoho.lesson,update', $lesson, time());
        //$this->puchCloudData('edusoho.course.lesson.update', 'update', 'lesson', $lesson['id'], time());
    }

    public function onCourseLessonDelete(ServiceEvent $event)
    {
        $context = $event->getSubject();
        $lesson  = $context['lesson'];
        $jobs    = $this->getCrontabService()->findJobByTargetTypeAndTargetId('lesson', $lesson['id']);

        if ($jobs) {
            $this->deleteJob($jobs);
        }

        $this->getCloudDataService()->push('edusoho.lesson.delete', $lesson['id'], time());
    }

    public function onCoursePublish(ServiceEvent $event)
    {
        $course = $event->getSubject();

        $from = array(
            'type'  => 'course',
            'id'    => $course['id'],
            'image' => $this->getFileUrl($course['smallPicture'])
        );

        $to = array('type' => 'course', 'id' => $course['id']);

        $body = array('type' => 'course.open');

        $this->getCloudDataService()->push('edusoho.course.publish', $course, time());

        //return $this->push($course['title'], '课程已发布!', $from, $to, $body);
    }

    public function onAnnouncementCreate(ServiceEvent $event)
    {
        $announcement = $event->getSubject();

        $target = $this->getTarget($announcement['targetType'], $announcement['targetId']);

        $from = array(
            'type'  => $target['type'],
            'id'    => $target['id'],
            'image' => $target['image']
        );

        $to = array(
            'type' => $target['type'],
            'id'   => $target['id']
        );

        $body = array(
            'id'   => $announcement['id'],
            'type' => 'announcement.create'
        );

        $this->getCloudDataService()->push('edusoho.announcement.create', $announcement, time());

        //$this->push($target['title'], $announcement['content'], $from, $to, $body);
    }

    public function onClassroomJoin(ServiceEvent $event)
    {
        $classroom = $event->getSubject();
        $userId    = $event->getArgument('userId');

        if ($classroom['conversationId']) {
            $this->addGroupMember('classroom', $classroom['conversationId'], $classroom['createdTime'], $userId);
        }

        $from = array(
            'type'  => 'classroom',
            'id'    => $classroom['id'],
            'image' => $this->getFileUrl($classroom['smallPicture'])
        );

        $to = array(
            'type' => 'classroom',
            'id'   => $classroom['id']
        );

        $body = array('type' => 'classroom.join', 'userId' => $userId);

        $this->getCloudDataService()->push('edusoho.classroom.join', $classroom, time());

        //$this->push($classroom['title'], '班级有新成员加入', $from, $to, $body);
    }

    public function onClassroomQuit(ServiceEvent $event)
    {
        $classroom = $event->getSubject();
        $userId    = $event->getArgument('userId');

        if ($classroom['conversationId']) {
            $this->deleteGroupMember('course', $classroom['conversationId'], $classroom['createdTime'], $userId);
        }
    }

    public function onCourseJoin(ServiceEvent $event)
    {
        $course = $event->getSubject();
        $userId = $event->getArgument('userId');

        if (!$course['parentId'] && $course['conversationId']) {
            $this->addGroupMember('course', $course['conversationId'], $course['createdTime'], $userId);
        }
    }

    public function onCourseQuit(ServiceEvent $event)
    {
        $course = $event->getSubject();
        $userId = $event->getArgument('userId');

        if (!$course['parentId'] && $course['conversationId']) {
            $this->deleteGroupMember('course', $course['conversationId'], $course['createdTime'], $userId);
        }
    }

    public function onClassroomPutCourse(ServiceEvent $event)
    {
        $classroomCourse = $event->getSubject();

        $classroom = $this->getClassroomService()->getClassroom($classroomCourse['classroomId']);

        $from = array(
            'type'  => 'classroom',
            'id'    => $classroom['id'],
            'image' => $this->getFileUrl($classroom['smallPicture'])
        );

        $to = array(
            'type' => 'classroom',
            'id'   => $classroom['id']
        );

        $course = $this->getCourseService()->getCourse($classroomCourse['courseId']);

        $body = array(
            'type'    => 'classroom.put_course',
            'id'      => $course['id'],
            'title'   => $course['title'],
            'image'   => $this->getFileUrl($course['smallPicture']),
            'content' => $course['about']
        );

        $this->getCloudDataService()->push('edusoho.classroom.put.course', $classroomCourse, time());

        //$this->push($classroom['title'], '班级有新课程加入！', $from, $to, $body);
    }

    public function onArticleCreate(ServiceEvent $event)
    {
        $article    = $event->getSubject();
        $schoolUtil = new MobileSchoolUtil();
        $articleApp = $schoolUtil->getArticleApp();
        $from       = array(
            'id'    => $articleApp['id'],
            'type'  => $articleApp['code'],
            'image' => $this->getAssetUrl($articleApp['avatar'])
        );

        $to = array(
            'type' => 'global'
        );

        $body = array(
            'type'    => 'news.create',
            'id'      => $article['id'],
            'title'   => $article['title'],
            'image'   => $this->getFileUrl($article['thumb']),
            'content' => $this->plainText($article['body'], 50)
        );
        $this->getCloudDataService()->push('edusho.article.create', $article, time());
        //$this->push('资讯', $article['title'], $from, $to, $body);
    }

    public function onCourseThreadPostCreate(ServiceEvent $event)
    {
        $post     = $event->getSubject();
        $course   = $this->getCourseService()->getCourse($post['courseId']);
        $question = $this->getThreadService()->getThread($post['courseId'], $post['threadId']);

        foreach ($course['teacherIds'] as $teacherId) {
            if ($teacherId == $post['userId'] && $question['type'] == 'question') {
                $target = $this->getTarget('course', $post['courseId']);
                $from   = array(
                    'type'  => 'course',
                    'id'    => $post['courseId'],
                    'image' => $target['image']
                );
                $to   = array('type' => 'user', 'id' => $question['userId']);
                $body = array(
                    'type'                => 'question.answered',
                    'threadId'            => $question['id'],
                    'courseId'            => $question['courseId'],
                    'lessonId'            => $question['lessonId'],
                    'questionCreatedTime' => $question['createdTime'],
                    'questionTitle'       => $question['title'],
                    'postContent'         => $post['content']
                );

                $this->getCloudDataService()->push('edusho.course.thread.posy.create', $course, time());
                // $this->push($course['title'], $question['title'], $from, $to, $body);
            }
        }
    }

    public function onHomeworkCheck(ServiceEvent $event)
    {
        $homeworkResult = $event->getSubject();

        $course = $this->getCourseService()->getCourse($homeworkResult['courseId']);
        $lesson = $this->getCourseService()->getLesson($homeworkResult['lessonId']);
        $target = $this->getTarget('course', $homeworkResult['courseId']);
        $from   = array(
            'type'  => 'course',
            'id'    => $homeworkResult['courseId'],
            'image' => $target['image']
        );
        $to   = array('type' => 'user', 'id' => $homeworkResult['userId']);
        $body = array(
            'type'             => 'homework.reviewed',
            'homeworkId'       => $homeworkResult['homeworkId'],
            'homeworkResultId' => $homeworkResult['id'],
            'lessonId'         => $homeworkResult['lessonId'],
            'courseId'         => $homeworkResult['courseId'],
            'teacherSay'       => $homeworkResult['teacherSay']
        );

        $this->getCloudDataService()->push('edusoho.homework.check', $homeworkResult, time());

        //$this->push($course['title'], $lesson['title'], $from, $to, $body);
    }

    public function onCourseLessonFinish(ServiceEvent $event)
    {
        $lesson = $event->getSubject();
        $course = $event->getArgument('course');
        $learn  = $event->getArgument('learn');

        $target = $this->getTarget('course', $learn['courseId']);
        $from   = array(
            'type'  => 'course',
            'id'    => $learn['courseId'],
            'image' => $target['image']
        );
        $to   = array('type' => 'user', 'id' => $learn['userId']);
        $body = array(
            'type'            => 'lesson.finish',
            'lessonId'        => $learn['lessonId'],
            'courseId'        => $learn['courseId'],
            'learnStartTime'  => $learn['startTime'],
            'learnFinishTime' => $learn['finishedTime']
        );

        $this->getCloudDataService()->push('edusoho.lesson.finish', $lesson, time());
        //$this->push($course['title'], $lesson['title'], $from, $to, $body);
    }

    public function onCourseLessonStart(ServiceEvent $event)
    {
        $lesson = $event->getSubject();
        $course = $event->getArgument('course');
        $learn  = $event->getArgument('learn');
        $target = $this->getTarget('course', $learn['courseId']);
        $from   = array(
            'type'  => 'course',
            'id'    => $learn['courseId'],
            'image' => $target['image']
        );
        $to   = array('type' => 'user', 'id' => $learn['userId']);
        $body = array(
            'type'           => 'lesson.start',
            'lessonId'       => $learn['lessonId'],
            'courseId'       => $learn['courseId'],
            'learnStartTime' => $learn['startTime']
        );
        $this->getCloudDataService()->push('edusoho.course.lesson.start', $lesson, time());
        //$this->push($course['title'], $lesson['title'], $from, $to, $body);
    }

    public function onCourseThreadCreate(ServiceEvent $event)
    {
        $thread = $event->getSubject();
        $course = $this->getCourseService()->getCourse($thread['courseId']);

        if ($thread['type'] == 'question') {
            $target = $this->getTarget('course', $thread['courseId']);
            $from   = array(
                'type'  => 'course',
                'id'    => $thread['courseId'],
                'image' => $target['image']
            );
            $to   = array('type' => 'user');
            $body = array(
                'type'                => 'question.created',
                'threadId'            => $thread['id'],
                'courseId'            => $thread['courseId'],
                'lessonId'            => $thread['lessonId'],
                'questionCreatedTime' => $thread['createdTime'],
                'questionTitle'       => $thread['title']
            );

            foreach ($course['teacherIds'] as $teacherId) {
                $to['id'] = $teacherId;
                $this->getCloudDataService()->push('edusoho.course.thread.create', $thread, time());
                //$this->push($course['title'], $thread['title'], $from, $to, $body);
            }
        }
    }

    public function pushCloudData(ServiceEvernt $event)
    {
        $data = $event->getSubject();
        $this->getCloudDataService()->push('edusoho.'.$event->getName(), $data, time());
    }

    public function onUserUpdate(Service $event)
    {
        $context = $event->getSubject();
        $user    = $context['user'];
        $this->getCloudDataService()->push('edusoho.user.update', $user, time());
    }

    public function onProfileUpdate(Service $event)
    {
        $context = $event->getSubject();
        $user    = $context['user'];
        $this->getCloudDataService()->push('edusoho.profile.update', $user, time());
    }

    public function onCourseUpdate(Service $event)
    {
        $context = $event->getSubject();
        $course  = $context['course'];
        $this->getCloudDataService()->push('eudsoho.course.update', $course, time());
    }

    public function onCourseLessonCreate(Service $event)
    {
        $context = $event->getSubject();
        $lesson  = $context['lesson'];
        $this->getCloudDataService()->push('eudsoho.lesson.create', $lesson, time());
    }

    protected function push($title, $content, $from, $to, $body)
    {
        $message = array(
            'title'   => $title,
            'content' => $content,
            'custom'  => array(
                'from' => $from,
                'to'   => $to,
                'body' => $body
            )
        );

        $result = CloudAPIFactory::create('tui')->post('/message/send', $message);
    }

    protected function addGroupMember($grouType, $conversationId, $timestamp, $memberId)
    {
        $user   = $this->getUserService()->getUser($memberId);
        $result = $this->getCloudDataService()->push('edusoho.'.$grouType.'.join', array(
            'conversationId' => $conversationId,
            'memberId'       => $memberId,
            'memberName'     => $user['nickname']
        ), $timestamp);
    }

    protected function deleteGroupMember($grouType, $conversationId, $timestamp, $memberId)
    {
        $result = $this->getCloudDataService()->push('edusoho.'.$grouType.'.quit', array(
            'conversationId' => $conversationId,
            'memberId'       => $memberId
        ), $timestamp);
    }

    protected function getTarget($type, $id)
    {
        $target = array('type' => $type, 'id' => $id);

        switch ($type) {
            case 'course':
                ;
                $course          = $this->getCourseService()->getCourse($id);
                $target['title'] = $course['title'];
                $target['image'] = $this->getFileUrl($course['smallPicture']);
                break;
            case 'classroom':
                ;
                $classroom       = $this->getClassroomService()->getClassroom($id);
                $target['title'] = $classroom['title'];
                $target['image'] = $this->getFileUrl($classroom['smallPicture']);
            case 'global':
                ;
                $schoolUtil      = new MobileSchoolUtil();
                $schoolApp       = $schoolUtil->getAnnouncementApp();
                $target['title'] = '网校公告';
                $target['id']    = $schoolApp['id'];
                $target['image'] = $this->getFileUrl($schoolApp['avatar']);
            default:
                # code...
                break;
        }

        return $target;
    }

    protected function getFileUrl($path)
    {
        if (empty($path)) {
            return $path;
        }

        $path = str_replace('public://', '', $path);
        $path = str_replace('files/', '', $path);
        $path = "http://{$_SERVER['HTTP_HOST']}/files/{$path}";
        return $path;
    }

    protected function getAssetUrl($path)
    {
        if (empty($path)) {
            return '';
        }

        $path = "http://{$_SERVER['HTTP_HOST']}/assets/{$path}";
        return $path;
    }

    protected function plainText($text, $count)
    {
        return mb_substr($text, 0, $count, 'utf-8');
    }

    protected function createJob($lesson)
    {
        if ($lesson['startTime'] >= (time() + 60 * 60)) {
            $startJob = array(
                'name'       => "PushNotificationOneHourJob",
                'cycle'      => 'once',
                'time'       => $lesson['startTime'] - 60 * 60,
                'jobClass'   => 'Topxia\\Service\\Notification\\Job\\PushNotificationOneHourJob',
                'targetType' => 'lesson',
                'targetId'   => $lesson['id']
            );
            $startJob = $this->getCrontabService()->createJob($startJob);
        }
    }

    protected function deleteJob($jobs)
    {
        foreach ($jobs as $key => $job) {
            if ($job['name'] == 'PushNotificationOneHourJob') {
                $this->getCrontabService()->deleteJob($job['id']);
            }
        }
    }

    protected function getThreadService()
    {
        return ServiceKernel::instance()->createService('Course.ThreadService');
    }

    protected function getCourseService()
    {
        return ServiceKernel::instance()->createService('Course.CourseService');
    }

    protected function getClassroomService()
    {
        return ServiceKernel::instance()->createService('Classroom:Classroom.ClassroomService');
    }

    protected function getUserService()
    {
        return ServiceKernel::instance()->createService('User.UserService');
    }

    protected function getTestpaperService()
    {
        return ServiceKernel::instance()->createService('Testpaper.TestpaperService');
    }

    protected function getCloudDataService()
    {
        return ServiceKernel::instance()->createService('CloudData.CloudDataService');
    }

    protected function getCrontabService()
    {
        return ServiceKernel::instance()->createService('Crontab.CrontabService');
    }

    protected function getSettingService()
    {
        return ServiceKernel::instance()->createService('System.SettingService');
    }
}
