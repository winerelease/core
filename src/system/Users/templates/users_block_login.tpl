{modgetvar module='Users' name='loginviaoption' assign='loginviaoption'}

<form action="{modurl modname="Users" type="user" func="login"}" method="post">
    <div style="text-align:left">
        <input type="hidden" name="url" value="{$returnurl|safetext}" />
        <input type="hidden" name="authid" value="{insert name="generateauthkey" module="Users"}" />
        <input id="users_authmodule" type="hidden" name="authmodule" value="{$authmodule}" />

        {* In the future, somewhere around here we would choose an authmodule from $authmodules, storing it in $authmodule. *}
        {* The prompts for authinfo below would be appropriate for the authmodule, and the form would adjust if a different authmodule was selected. *}

        <div><label for="loginblock_authinfo_loginid">{if $loginviaoption == 1}{gt text="E-mail address" domain='zikula'}{else}{gt text="User name" domain='zikula'}{/if}</label></div>
        <div><input id="loginblock_authinfo_loginid" type="text" name="authinfo[loginid]" size="14" maxlength="64" value="" /></div>

        <div><label for="loginblock_authinfo_pass">{gt text="Password" domain='zikula'}</label></div>
        <div><input id="loginblock_authinfo_pass" type="password" name="authinfo[pass]" size="14" maxlength="20" /></div>
        {if $seclevel != 'High'}
        <input id="loginblock_rememberme" type="checkbox" value="1" name="rememberme" />
        <label for="loginblock_rememberme">{gt text="Remember me" domain='zikula'}</label>
        {/if}
        <div><input type="submit" value="{gt text="Log in" domain='zikula'}" /></div>
        <ul>
            {if $allowregistration}
            <li><a href="{modurl modname='Users' func='register'}">{gt text="New account" domain='zikula'}</a></li>
            {/if}
            <li><a href="{modurl modname='Users' func='lostpwduname'}">{gt text="Login problems?" domain='zikula'}</a></li>
        </ul>
    </div>
</form>
