<div class="block">
    <div class="element">
    <label>{'Path offset'|i18n( 'extension/autorss' )}:</label>
    <input type="text" name="PathOffset_{$event.id}" value="{$event.path_offset}" />
    </div>

    <div class="element">
    <label><input name ="Defer_{$event.id}" type="checkbox" {if $event.defer}checked="checked"{/if} /> {'Defer to cron'|i18n( 'extension/autorss' )}</label>
    </div>
</div>

<div class="block">
    <fieldset>
    <legend>{'Sources'|i18n( 'extension/autorss' )}</legend>

    {let $attributeMappings=ezini('GeneralSettings','Mappings','autorss.ini')}
    {foreach $attributeMappings as $mappingIdentifier => $mappingName}
    <label><input name="AttributeMappings_{$event.id}[]" type="checkbox" value="{$mappingIdentifier|wash}" {if $event.attribute_mappings|contains($mappingIdentifier)}checked="chekced"{/if}> {$mappingName}</label>
    {/foreach}
    </select>

    </fieldset>
</div>