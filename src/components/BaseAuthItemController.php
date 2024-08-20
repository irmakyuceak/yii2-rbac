<?php

/* Inspired by https://github.com/yii2mod/yii2-rbac (@yii2mod) and https://github.com/mdmsoft/yii2-admin (@mdmsoft) */

namespace portalium\rbac\components;

use Yii;
use yii\base\NotSupportedException;
use yii\filters\VerbFilter;
use yii\rbac\Item;
use yii\web\NotFoundHttpException;
use portalium\rbac\models\AuthItem;
use portalium\rbac\models\AuthItemSearch;
use portalium\rbac\Module;
use portalium\base\Event;
use portalium\web\Controller as WebController;

/**
 * BaseAuthItemController implements the CRUD actions for AuthItem model.
 *
 * @property integer $type
 * @property array $labels
 *
 */
class BaseAuthItemController extends WebController
{
    const EVENT_AFTER_DELETE = 'afterDelete';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['post'],
                    'assign' => ['post'],
                    'remove' => ['post'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        Yii::$app->view->registerJs(
            "
            $.ajaxSetup({
                beforeSend: function(xhr){
                    this.data += '&' + $.param({
                        '" . Yii::$app->request->csrfParam . "': '" . Yii::$app->request->getCsrfToken() . "'
                    });
                }
                });
            "
        );

        return parent::beforeAction($action);
    }

    /**
     * Lists all AuthItem models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new AuthItemSearch(['type' => $this->type]);
        $dataProvider = $searchModel->search($this->request->getQueryParams());
        // $dataProvider->pagination->pageSize = 24;
        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * Updates an existing AuthItem model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param  string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $oldModel = clone $model;
        if ($model->load($this->request->post())) {
            $this->updateSettingAssignableRoles($model, $oldModel);
            Event::trigger(Yii::$app->getModules(), Module::EVENT_ITEM_UPDATE, new Event(['payload' => ['item' => $model, 'oldItem' => $oldModel]]));
            if($model->save()) {
                Yii::$app->session->setFlash('success', Module::t('The role has been updated successfully.'));
                return $this->redirect(['view', 'id' => $model->name]);
            }
        }
        return $this->render('update', ['model' => $model]);
    }

    private function updateSettingAssignableRoles($model, $oldModel)
    {
        $setting = Yii::$app->setting->getSetting('workspace::available_roles');
        if ($setting) {
            $modules = json_decode($setting->value);
            foreach ($modules as $key => $value) {
                if (in_array($oldModel->name, $value)) {
                    $value = array_diff($value, [$oldModel->name]);
                    $value[] = $model->name;
                    $modules->$key = $value;
                }
            }
            $setting->value = json_encode($modules);
            $setting->save();
        }
    }

    /**
     * Deletes an existing AuthItem model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param  string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        Event::trigger(Yii::$app->getModules(), Module::EVENT_ITEM_DELETE, new Event(['payload' => ['item' => $model]]));
        $this->deleteSettingAssignableRoles($model);
        Yii::$app->authManager->remove($model->item);
        Yii::$app->session->setFlash('success', Module::t('The role was deleted successfully.'));

        return $this->redirect(['index']);
    }

    private function deleteSettingAssignableRoles($model)
    {
        $setting = Yii::$app->setting->getSetting('workspace::available_roles');
        if ($setting) {
            $modules = json_decode($setting->value);
            foreach ($modules as $key => $value) {
                if (in_array($model->name, $value)) {
                    $value = array_diff($value, [$model->name]);
                    $modules->$key = $value;
                }
            }
            $setting->value = json_encode($modules);
            $setting->save();
        }
    }

    /**
     * Creates a new AuthItem model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new AuthItem();
        $model->type = $this->type;
        if ($model->load($this->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', Module::t('The role was created successfully.'));
            return $this->redirect(['view', 'id' => $model->name]);
        } else {
            return $this->render('create', ['model' => $model]);
        }
    }

    /**
     * Displays a single AuthItem model.
     * @param  string $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        return $this->render('view', ['model' => $model]);
    }

    /**
     * Assign items
     * @param string $id
     * @return array
     */
    public function actionAssign($id)
    {
        $items = $this->request->post('items', []);
        $model = $this->findModel($id);
        $success = $model->addChildren($items);
        Yii::$app->getResponse()->format = 'json';

        return array_merge($model->getItems(), ['success' => $success]);
    }

    /**
     * Assign items
     * @param string $id
     * @return array
     */
    public function actionGetUsers($id)
    {
        $page = $this->request->get('page', 0);
        $model = $this->findModel($id);
        Yii::$app->getResponse()->format = 'json';

        return array_merge($model->getUsers($page));
    }

    /**
     * Assign or remove items
     * @param string $id
     * @return array
     */
    public function actionRemove($id)
    {
        $items = $this->request->post('items', []);
        $model = $this->findModel($id);
        $success = $model->removeChildren($items);
        Yii::$app->getResponse()->format = 'json';

        return array_merge($model->getItems(), ['success' => $success]);
    }

    /**
     * Label use in view
     * @throws NotSupportedException
     */
    public function labels()
    {
        throw new NotSupportedException(get_class($this) . ' does not support labels().');
    }

    /**
     * Type of Auth Item.
     * @return integer
     */
    public function getType()
    {
    }

    /**
     * Finds the AuthItem model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return AuthItem the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $item = $this->type === Item::TYPE_ROLE ? Yii::$app->authManager->getRole($id) : Yii::$app->authManager->getPermission($id);
        if ($item) {
            return new AuthItem($item);
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
