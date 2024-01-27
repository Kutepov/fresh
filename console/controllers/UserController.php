<?php namespace console\controllers;

use common\models\User;
use yii\console\widgets\Table;
use yii;
use yii\console\ExitCode;
use yii\rbac\ManagerInterface;

class UserController extends Controller
{
    private function rbacInit(ManagerInterface $auth)
    {
        $admin = $auth->createRole('admin');
        $auth->add($admin);
        return ExitCode::OK;
    }

    public function actionCreate(string $email)
    {
        try {
            $auth = Yii::$app->authManager;
            if (!$auth->getRole('admin')) {
                $this->rbacInit($auth);
            }

            $user = new User();
            $user->email = $email;
            $user->status = User::STATUS_ACTIVE;
            $user->generateAuthKey();
            $password = Yii::$app->security->generateRandomString(6);
            $user->password = $password;

            if ($user->save()) {
                $auth = Yii::$app->authManager;
                $authorRole = $auth->getRole('admin');
                $auth->assign($authorRole, $user->getId());
                echo Table::widget([
                    'headers' => ['Email', 'Password'],
                    'rows' => [
                        [$email, $password],
                    ],
                ]);
                return ExitCode::OK;
            }
            else {
                $this->showErrors($user->errors);
                return ExitCode::DATAERR;
            }
        }
        catch (\Exception $exception) {
            $this->stderr($exception->getMessage());
            return ExitCode::DATAERR;
        }
    }

    private function showErrors($errors)
    {
        $rows = [];
        foreach ($errors as $index => $error) {
            $value = '';
            foreach ($error as $item) {
                $value .= $item."\n";
            }
            $rows[] = [$index, $value];
        }
        echo Table::widget([
            'headers' => ['Field', 'Value'],
            'rows' => $rows
        ]);
    }
}