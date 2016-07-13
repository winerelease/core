<?php
/**
 * Routes.
 *
 * @copyright Zikula contributors (Zikula)
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @author Zikula contributors <support@zikula.org>.
 * @link http://www.zikula.org
 * @link http://zikula.org
 * @version Generated by ModuleStudio 0.7.0 (http://modulestudio.de).
 */

namespace Zikula\RoutesModule\Controller\Base;

use Zikula\RoutesModule\Entity\RouteEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FormUtil;
use ModUtil;
use RuntimeException;
use System;
use ZLanguage;
use Zikula\Component\SortableColumns\Column;
use Zikula\Component\SortableColumns\SortableColumns;
use Zikula\Core\Controller\AbstractController;
use Zikula\Core\ModUrl;
use Zikula\Core\RouteUrl;
use Zikula\Core\Response\PlainResponse;
use Zikula\ThemeModule\Engine\Annotation\Theme;

/**
 * Route controller base class.
 */
class RouteController extends AbstractController
{
    /**
     * This is the default action handling the main admin area called without defining arguments.
     * @Theme("admin")
     * @Cache(expires="+7 days", public=true)
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function adminIndexAction(Request $request)
    {
        return $this->indexInternal($request, true);
    }
    
    /**
     * This is the default action handling the main area called without defining arguments.
     * @Cache(expires="+7 days", public=true)
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function indexAction(Request $request)
    {
        return $this->indexInternal($request, false);
    }
    
    /**
     * This method includes the common implementation code for adminIndex() and index().
     */
    protected function indexInternal(Request $request, $isAdmin = false)
    {
        $controllerHelper = $this->get('zikula_routes_module.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = ['controller' => 'route', 'action' => 'main'];
        $permLevel = $isAdmin ? ACCESS_ADMIN : ACCESS_OVERVIEW;
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        
        if ($isAdmin) {
            
            return $this->redirectToRoute('zikularoutesmodule_route_' . ($isAdmin ? 'admin' : '') . 'view');
        }
        
        $templateParameters = [
            'routeArea' => $isAdmin ? 'admin' : ''
        ];
        
        // return index template
        return $this->render('@ZikulaRoutesModule/Route/index.html.twig', $templateParameters);
    }
    /**
     * This action provides an item list overview in the admin area.
     * @Theme("admin")
     * @Cache(expires="+2 hours", public=false)
     *
     * @param Request  $request      Current request instance.
     * @param string  $sort         Sorting field.
     * @param string  $sortdir      Sorting direction.
     * @param int     $pos          Current pager position.
     * @param int     $num          Amount of entries to display.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function adminViewAction(Request $request, $sort, $sortdir, $pos, $num)
    {
        return $this->viewInternal($request, $sort, $sortdir, $pos, $num, true);
    }
    
    /**
     * This action provides an item list overview.
     * @Cache(expires="+2 hours", public=false)
     *
     * @param Request  $request      Current request instance.
     * @param string  $sort         Sorting field.
     * @param string  $sortdir      Sorting direction.
     * @param int     $pos          Current pager position.
     * @param int     $num          Amount of entries to display.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function viewAction(Request $request, $sort, $sortdir, $pos, $num)
    {
        return $this->viewInternal($request, $sort, $sortdir, $pos, $num, false);
    }
    
    /**
     * This method includes the common implementation code for adminView() and view().
     */
    protected function viewInternal(Request $request, $sort, $sortdir, $pos, $num, $isAdmin = false)
    {
        $controllerHelper = $this->get('zikula_routes_module.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = ['controller' => 'route', 'action' => 'view'];
        $permLevel = $isAdmin ? ACCESS_ADMIN : ACCESS_READ;
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        // temporary workarounds
        // let repository know if we are in admin or user area
        $request->query->set('lct', $isAdmin ? 'admin' : 'user');
        // let entities know if we are in admin or user area
        System::queryStringSetVar('lct', $isAdmin ? 'admin' : 'user');
        
        $repository = $this->get('zikula_routes_module.' . $objectType . '_factory')->getRepository();
        $repository->setRequest($request);
        $viewHelper = $this->get('zikula_routes_module.view_helper');
        $templateParameters = [
            'routeArea' => $isAdmin ? 'admin' : ''
        ];
        
        // convenience vars to make code clearer
        $currentUrlArgs = [];
        $where = '';
        
        $showOwnEntries = $request->query->getInt('own', $this->getVar('showOnlyOwnEntries', 0));
        $showAllEntries = $request->query->getInt('all', 0);
        
        $templateParameters['showOwnEntries'] = $showOwnEntries;
        $templateParameters['showAllEntries'] = $showAllEntries;
        if ($showOwnEntries == 1) {
            $currentUrlArgs['own'] = 1;
        }
        if ($showAllEntries == 1) {
            $currentUrlArgs['all'] = 1;
        }
        
        $additionalParameters = $repository->getAdditionalTemplateParameters('controllerAction', $utilArgs);
        
        $resultsPerPage = 0;
        if ($showAllEntries != 1) {
            // the number of items displayed on a page for pagination
            $resultsPerPage = $num;
            if ($resultsPerPage == 0) {
                $resultsPerPage = $this->getVar('pageSize', 10);
            }
        }
        
        // parameter for used sorting field
        if (empty($sort) || !in_array($sort, $repository->getAllowedSortingFields())) {
            $sort = $repository->getDefaultSortingField();
            System::queryStringSetVar('sort', $sort);
            $request->query->set('sort', $sort);
            // set default sorting in route parameters (e.g. for the pager)
            $routeParams = $request->attributes->get('_route_params');
            $routeParams['sort'] = $sort;
            $request->attributes->set('_route_params', $routeParams);
        }
        
        // parameter for used sort order
        $sortdir = strtolower($sortdir);
        
        $sortableColumns = new SortableColumns($this->get('router'), 'zikularoutesmodule_route_' . ($isAdmin ? 'admin' : '') . 'view', 'sort', 'sortdir');
        $sortableColumns->addColumns([
            new Column('routeType'),
            new Column('replacedRouteName'),
            new Column('bundle'),
            new Column('controller'),
            new Column('action'),
            new Column('path'),
            new Column('host'),
            new Column('schemes'),
            new Column('methods'),
            new Column('prependBundlePrefix'),
            new Column('translatable'),
            new Column('translationPrefix'),
            new Column('condition'),
            new Column('description'),
            new Column('sort'),
            new Column('group'),
            new Column('createdUserId'),
            new Column('createdDate'),
            new Column('updatedUserId'),
            new Column('updatedDate'),
        ]);
        $sortableColumns->setOrderBy($sortableColumns->getColumn($sort), strtoupper($sortdir));
        
        $additionalUrlParameters = [
            'all' => $showAllEntries,
            'own' => $showOwnEntries,
            'pageSize' => $resultsPerPage
        ];
        $additionalUrlParameters = array_merge($additionalUrlParameters, $additionalParameters);
        $sortableColumns->setAdditionalUrlParameters($additionalUrlParameters);
        
        $selectionArgs = [
            'ot' => $objectType,
            'where' => $where,
            'orderBy' => $sort . ' ' . $sortdir
        ];
        if ($showAllEntries == 1) {
            // retrieve item list without pagination
            $entities = ModUtil::apiFunc($this->name, 'selection', 'getEntities', $selectionArgs);
        } else {
            // the current offset which is used to calculate the pagination
            $currentPage = $pos;
        
            // retrieve item list with pagination
            $selectionArgs['currentPage'] = $currentPage;
            $selectionArgs['resultsPerPage'] = $resultsPerPage;
            list($entities, $objectCount) = ModUtil::apiFunc($this->name, 'selection', 'getEntitiesPaginated', $selectionArgs);
        
            $templateParameters['currentPage'] = $currentPage;
            $templateParameters['pager'] = ['numitems' => $objectCount, 'itemsperpage' => $resultsPerPage];
        }
        
        foreach ($entities as $k => $entity) {
            $entity->initWorkflow();
        }
        
        // build ModUrl instance for display hooks
        $currentUrlObject = new ModUrl($this->name, 'route', 'view', ZLanguage::getLanguageCode(), $currentUrlArgs);
        
        $templateParameters['items'] = $entities;
        $templateParameters['sort'] = $sort;
        $templateParameters['sdir'] = $sortdir;
        $templateParameters['pagesize'] = $resultsPerPage;
        $templateParameters['currentUrlObject'] = $currentUrlObject;
        $templateParameters = array_merge($templateParameters, $additionalParameters);
        
        $formOptions = [
            'all' => $templateParameters['showAllEntries'],
            'own' => $templateParameters['showOwnEntries']
        ];
        $form = $this->createForm('Zikula\RoutesModule\Form\Type\QuickNavigation\\' . ucfirst($objectType) . 'QuickNavType', $templateParameters, $formOptions);
        
        $templateParameters['sort'] = $sortableColumns->generateSortableColumns();
        $templateParameters['quickNavForm'] = $form->createView();
        
        
        
        $modelHelper = $this->get('zikula_routes_module.model_helper');
        $templateParameters['canBeCreated'] = $modelHelper->canBeCreated($objectType);
        
        // fetch and return the appropriate template
        return $viewHelper->processTemplate($this->get('twig'), $objectType, 'view', $request, $templateParameters);
    }
    /**
     * This action provides a item detail view in the admin area.
     * @Theme("admin")
     * @ParamConverter("route", class="ZikulaRoutesModule:RouteEntity", options={"id" = "id", "repository_method" = "selectById"})
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance.
     * @param RouteEntity $route      Treated route instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by param converter if item to be displayed isn't found.
     */
    public function adminDisplayAction(Request $request, RouteEntity $route)
    {
        return $this->displayInternal($request, $route, true);
    }
    
    /**
     * This action provides a item detail view.
     * @ParamConverter("route", class="ZikulaRoutesModule:RouteEntity", options={"id" = "id", "repository_method" = "selectById"})
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance.
     * @param RouteEntity $route      Treated route instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by param converter if item to be displayed isn't found.
     */
    public function displayAction(Request $request, RouteEntity $route)
    {
        return $this->displayInternal($request, $route, false);
    }
    
    /**
     * This method includes the common implementation code for adminDisplay() and display().
     */
    protected function displayInternal(Request $request, RouteEntity $route, $isAdmin = false)
    {
        $controllerHelper = $this->get('zikula_routes_module.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = ['controller' => 'route', 'action' => 'display'];
        $permLevel = $isAdmin ? ACCESS_ADMIN : ACCESS_READ;
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        // temporary workarounds
        // let repository know if we are in admin or user area
        $request->query->set('lct', $isAdmin ? 'admin' : 'user');
        // let entities know if we are in admin or user area
        System::queryStringSetVar('lct', $isAdmin ? 'admin' : 'user');
        
        $repository = $this->get('zikula_routes_module.' . $objectType . '_factory')->getRepository();
        $repository->setRequest($request);
        
        $entity = $route;
        
        
        $entity->initWorkflow();
        
        // build ModUrl instance for display hooks; also create identifier for permission check
        $currentUrlArgs = $entity->createUrlArgs();
        $instanceId = $entity->createCompositeIdentifier();
        $currentUrlArgs['id'] = $instanceId; // TODO remove this
        $currentUrlObject = new ModUrl($this->name, 'route', 'display', ZLanguage::getLanguageCode(), $currentUrlArgs);
        
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', $instanceId . '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        
        $viewHelper = $this->get('zikula_routes_module.view_helper');
        $templateParameters = [
            'routeArea' => $isAdmin ? 'admin' : ''
        ];
        $templateParameters[$objectType] = $entity;
        $templateParameters['currentUrlObject'] = $currentUrlObject;
        $templateParameters = array_merge($templateParameters, $repository->getAdditionalTemplateParameters('controllerAction', $utilArgs));
        
        // fetch and return the appropriate template
        return $viewHelper->processTemplate($this->get('twig'), $objectType, 'display', $request, $templateParameters);
    }
    /**
     * This action provides a handling of edit requests in the admin area.
     * @Theme("admin")
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by form handler if item to be edited isn't found.
     * @throws RuntimeException      Thrown if another critical error occurs (e.g. workflow actions not available).
     */
    public function adminEditAction(Request $request)
    {
        return $this->editInternal($request, true);
    }
    
    /**
     * This action provides a handling of edit requests.
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by form handler if item to be edited isn't found.
     * @throws RuntimeException      Thrown if another critical error occurs (e.g. workflow actions not available).
     */
    public function editAction(Request $request)
    {
        return $this->editInternal($request, false);
    }
    
    /**
     * This method includes the common implementation code for adminEdit() and edit().
     */
    protected function editInternal(Request $request, $isAdmin = false)
    {
        $controllerHelper = $this->get('zikula_routes_module.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = ['controller' => 'route', 'action' => 'edit'];
        $permLevel = $isAdmin ? ACCESS_ADMIN : ACCESS_EDIT;
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        // temporary workarounds
        // let repository know if we are in admin or user area
        $request->query->set('lct', $isAdmin ? 'admin' : 'user');
        // let entities know if we are in admin or user area
        System::queryStringSetVar('lct', $isAdmin ? 'admin' : 'user');
        
        $repository = $this->get('zikula_routes_module.' . $objectType . '_factory')->getRepository();
        
        $templateParameters = [
            'routeArea' => $isAdmin ? 'admin' : ''
        ];
        $templateParameters = array_merge($templateParameters, $repository->getAdditionalTemplateParameters('controllerAction', $utilArgs));
        
        // delegate form processing to the form handler
        $formHandler = $this->get('zikula_routes_module.form.handler.route');
        $formHandler->processForm($templateParameters);
        
        $viewHelper = $this->get('zikula_routes_module.view_helper');
        $templateParameters = $formHandler->getTemplateParameters();
        
        // fetch and return the appropriate template
        return $viewHelper->processTemplate($this->get('twig'), $objectType, 'edit', $request, $templateParameters);
    }
    /**
     * This action provides a handling of simple delete requests in the admin area.
     * @Theme("admin")
     * @ParamConverter("route", class="ZikulaRoutesModule:RouteEntity", options={"id" = "id", "repository_method" = "selectById"})
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance.
     * @param RouteEntity $route      Treated route instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by param converter if item to be deleted isn't found.
     * @throws RuntimeException      Thrown if another critical error occurs (e.g. workflow actions not available).
     */
    public function adminDeleteAction(Request $request, RouteEntity $route)
    {
        return $this->deleteInternal($request, $route, true);
    }
    
    /**
     * This action provides a handling of simple delete requests.
     * @ParamConverter("route", class="ZikulaRoutesModule:RouteEntity", options={"id" = "id", "repository_method" = "selectById"})
     * @Cache(lastModified="route.getUpdatedDate()", ETag="'Route' ~ route.getid() ~ route.getUpdatedDate().format('U')")
     *
     * @param Request  $request      Current request instance.
     * @param RouteEntity $route      Treated route instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     * @throws NotFoundHttpException Thrown by param converter if item to be deleted isn't found.
     * @throws RuntimeException      Thrown if another critical error occurs (e.g. workflow actions not available).
     */
    public function deleteAction(Request $request, RouteEntity $route)
    {
        return $this->deleteInternal($request, $route, false);
    }
    
    /**
     * This method includes the common implementation code for adminDelete() and delete().
     */
    protected function deleteInternal(Request $request, RouteEntity $route, $isAdmin = false)
    {
        $controllerHelper = $this->get('zikula_routes_module.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = ['controller' => 'route', 'action' => 'delete'];
        $permLevel = $isAdmin ? ACCESS_ADMIN : ACCESS_DELETE;
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        $entity = $route;
        
        $flashBag = $request->getSession()->getFlashBag();
        $logger = $this->get('logger');
        $logArgs = ['app' => 'ZikulaRoutesModule', 'user' => $this->get('zikula_users_module.current_user')->get('uname'), 'entity' => 'route', 'id' => $entity->createCompositeIdentifier()];
        
        $entity->initWorkflow();
        
        // determine available workflow actions
        $workflowHelper = $this->get('zikula_routes_module.workflow_helper');
        $actions = $workflowHelper->getActionsForObject($entity);
        if ($actions === false || !is_array($actions)) {
            $flashBag->add(\Zikula_Session::MESSAGE_ERROR, $this->__('Error! Could not determine workflow actions.'));
            $logger->error('{app}: User {user} tried to delete the {entity} with id {id}, but failed to determine available workflow actions.', $logArgs);
            throw new \RuntimeException($this->__('Error! Could not determine workflow actions.'));
        }
        
        // redirect to the list of routes
        $redirectRoute = 'zikularoutesmodule_route_' . ($isAdmin ? 'admin' : '') . 'view';
        
        // check whether deletion is allowed
        $deleteActionId = 'delete';
        $deleteAllowed = false;
        foreach ($actions as $actionId => $action) {
            if ($actionId != $deleteActionId) {
                continue;
            }
            $deleteAllowed = true;
            break;
        }
        if (!$deleteAllowed) {
            $flashBag->add(\Zikula_Session::MESSAGE_ERROR, $this->__('Error! It is not allowed to delete this route.'));
            $logger->error('{app}: User {user} tried to delete the {entity} with id {id}, but this action was not allowed.', $logArgs);
        
            return $this->redirectToRoute($redirectRoute);
        }
        
        $form = $this->createForm('Zikula\RoutesModule\Form\DeleteEntityType', $entity);
        
        if ($form->handleRequest($request)->isValid()) {
            if ($form->get('delete')->isClicked()) {
                $hookHelper = $this->get('zikula_routes_module.hook_helper');
                // Let any hooks perform additional validation actions
                $hookType = 'validate_delete';
                $validationHooksPassed = $hookHelper->callValidationHooks($entity, $hookType);
                if ($validationHooksPassed) {
                    // execute the workflow action
                    $success = $workflowHelper->executeAction($entity, $deleteActionId);
                    if ($success) {
                        $flashBag->add(\Zikula_Session::MESSAGE_STATUS, $this->__('Done! Item deleted.'));
                        $logger->notice('{app}: User {user} deleted the {entity} with id {id}.', $logArgs);
                    }
                    
                    // Let any hooks know that we have deleted the route
                    $hookType = 'process_delete';
                    $hookHelper->callProcessHooks($entity, $hookType, null);
                    
                    return $this->redirectToRoute($redirectRoute);
                }
            } elseif ($form->get('cancel')->isClicked()) {
                $this->addFlash(\Zikula_Session::MESSAGE_STATUS, $this->__('Operation cancelled.'));
        
                return $this->redirectToRoute($redirectRoute);
            }
        }
        
        $repository = $this->get('zikula_routes_module.' . $objectType . '_factory')->getRepository();
        
        $viewHelper = $this->get('zikula_routes_module.view_helper');
        $templateParameters = [
            'routeArea' => $isAdmin ? 'admin' : '',
            'deleteForm' => $form->createView()
        ];
        
        $templateParameters[$objectType] = $entity;
        $templateParameters = array_merge($templateParameters, $repository->getAdditionalTemplateParameters('controllerAction', $utilArgs));
        
        // fetch and return the appropriate template
        return $viewHelper->processTemplate($this->get('twig'), $objectType, 'delete', $request, $templateParameters);
    }
    /**
     * This is a custom action in the admin area.
     * @Theme("admin")
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function adminReloadAction(Request $request)
    {
        return $this->reloadInternal($request, true);
    }
    
    /**
     * This is a custom action.
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function reloadAction(Request $request)
    {
        return $this->reloadInternal($request, false);
    }
    
    /**
     * This method includes the common implementation code for adminReload() and reload().
     */
    protected function reloadInternal(Request $request, $isAdmin = false)
    {
        $controllerHelper = $this->get('zikula_routes_module.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = ['controller' => 'route', 'action' => 'reload'];
        $permLevel = $isAdmin ? ACCESS_ADMIN : ACCESS_OVERVIEW;
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        /** TODO: custom logic */
        
        $templateParameters = [
            'routeArea' => $isAdmin ? 'admin' : ''
        ];
        
        // return template
        return $this->render('@ZikulaRoutesModule/Route/reload.html.twig', $templateParameters);
    }
    /**
     * This is a custom action in the admin area.
     * @Theme("admin")
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function adminRenewAction(Request $request)
    {
        return $this->renewInternal($request, true);
    }
    
    /**
     * This is a custom action.
     *
     * @param Request  $request      Current request instance.
     *
     * @return mixed Output.
     *
     * @throws AccessDeniedException Thrown if the user doesn't have required permissions.
     */
    public function renewAction(Request $request)
    {
        return $this->renewInternal($request, false);
    }
    
    /**
     * This method includes the common implementation code for adminRenew() and renew().
     */
    protected function renewInternal(Request $request, $isAdmin = false)
    {
        $controllerHelper = $this->get('zikula_routes_module.controller_helper');
        
        // parameter specifying which type of objects we are treating
        $objectType = 'route';
        $utilArgs = ['controller' => 'route', 'action' => 'renew'];
        $permLevel = $isAdmin ? ACCESS_ADMIN : ACCESS_OVERVIEW;
        if (!$this->hasPermission($this->name . ':' . ucfirst($objectType) . ':', '::', $permLevel)) {
            throw new AccessDeniedException();
        }
        /** TODO: custom logic */
        
        $templateParameters = [
            'routeArea' => $isAdmin ? 'admin' : ''
        ];
        
        // return template
        return $this->render('@ZikulaRoutesModule/Route/renew.html.twig', $templateParameters);
    }

    /**
     * Process status changes for multiple items.
     *
     * This function processes the items selected in the admin view page.
     * Multiple items may have their state changed or be deleted.
     *
     * @param Request $request Current request instance.
     *
     * @return bool true on sucess, false on failure.
     *
     * @throws RuntimeException Thrown if executing the workflow action fails
     */
    public function adminHandleSelectedEntriesAction(Request $request)
    {
        return $this->handleSelectedEntriesActionInternal($request, true);
    }
    /**
     * Process status changes for multiple items.
     *
     * This function processes the items selected in the admin view page.
     * Multiple items may have their state changed or be deleted.
     *
     * @param Request $request Current request instance.
     *
     * @return bool true on sucess, false on failure.
     *
     * @throws RuntimeException Thrown if executing the workflow action fails
     */
    public function handleSelectedEntriesAction(Request $request)
    {
        return $this->handleSelectedEntriesActionInternal($request, false);
    }
    
    /**
     * This method includes the common implementation code for adminHandleSelectedEntriesAction() and handleSelectedEntriesAction().
     */
    protected function handleSelectedEntriesActionInternal(Request $request, $isAdmin = false)
    {
        $objectType = 'route';
        
        // Get parameters
        $action = $request->request->get('action', null);
        $items = $request->request->get('items', null);
        
        $action = strtolower($action);
        
        $workflowHelper = $this->get('zikula_routes_module.workflow_helper');
        $hookHelper = $this->get('zikula_routes_module.hook_helper');
        $flashBag = $request->getSession()->getFlashBag();
        $logger = $this->get('logger');
        $userName = $this->get('zikula_users_module.current_user')->get('uname');
        
        // process each item
        foreach ($items as $itemid) {
            // check if item exists, and get record instance
            $selectionArgs = [
                'ot' => $objectType,
                'id' => $itemid,
                'useJoins' => false
            ];
            $entity = ModUtil::apiFunc($this->name, 'selection', 'getEntity', $selectionArgs);
        
            $entity->initWorkflow();
        
            // check if $action can be applied to this entity (may depend on it's current workflow state)
            $allowedActions = $workflowHelper->getActionsForObject($entity);
            $actionIds = array_keys($allowedActions);
            if (!in_array($action, $actionIds)) {
                // action not allowed, skip this object
                continue;
            }
        
            // Let any hooks perform additional validation actions
            $hookType = $action == 'delete' ? 'validate_delete' : 'validate_edit';
            $validationHooksPassed = $hookHelper->callValidationHooks($entity, $hookType);
            if (!$validationHooksPassed) {
                continue;
            }
        
            $success = false;
            try {
                if (!$entity->validate()) {
                    continue;
                }
                // execute the workflow action
                $success = $workflowHelper->executeAction($entity, $action);
            } catch(\Exception $e) {
                $flashBag->add(\Zikula_Session::MESSAGE_ERROR, $this->__f('Sorry, but an unknown error occured during the %s action. Please apply the changes again!', [$action]));
                $logger->error('{app}: User {user} tried to execute the {action} workflow action for the {entity} with id {id}, but failed. Error details: {errorMessage}.', ['app' => 'ZikulaRoutesModule', 'user' => $userName, 'action' => $action, 'entity' => 'route', 'id' => $itemid, 'errorMessage' => $e->getMessage()]);
            }
        
            if (!$success) {
                continue;
            }
        
            if ($action == 'delete') {
                $flashBag->add(\Zikula_Session::MESSAGE_STATUS, $this->__('Done! Item deleted.'));
                $logger->notice('{app}: User {user} deleted the {entity} with id {id}.', ['app' => 'ZikulaRoutesModule', 'user' => $userName, 'entity' => 'route', 'id' => $itemid]);
            } else {
                $flashBag->add(\Zikula_Session::MESSAGE_STATUS, $this->__('Done! Item updated.'));
                $logger->notice('{app}: User {user} executed the {action} workflow action for the {entity} with id {id}.', ['app' => 'ZikulaRoutesModule', 'user' => $userName, 'action' => $action, 'entity' => 'route', 'id' => $itemid]);
            }
        
            // Let any hooks know that we have updated or deleted an item
            $hookType = $action == 'delete' ? 'process_delete' : 'process_edit';
            $url = null;
            if ($action != 'delete') {
                $urlArgs = $entity->createUrlArgs();
                $url = new RouteUrl('zikularoutesmodule_route_' . /*($isAdmin ? 'admin' : '') . */'display', $urlArgs);
            }
            $hookHelper->callProcessHooks($entity, $hookType, $url);
        }
        
        return $this->redirectToRoute('zikularoutesmodule_route_' . ($isAdmin ? 'admin' : '') . 'index');
    }
}
