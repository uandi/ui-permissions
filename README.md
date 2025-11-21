![QA](https://github.com/uandi/ui-permissions/actions/workflows/qa.yml/badge.svg)

# Deployable TYPO3 Permissions

TYPO3 extension to declaratively manage backend user group permissions as code.

If you have a TYPO3 project with any level of permissions for backend users you probably ran into one of the following problems:
- Creating the permissions in the TYPO3 backend takes a lot of time and clicks
- If you have different environments like INT, PPE and PROD, you would have to create the permissions on each environment
- You deployed something to PROD and forgot about the permissions until the customer calls because he can't see the new feature

This extension introduces deployable YAML descriptions of permissions that are gathered from all active extensions, merged, and written into the database via a CLI command. This makes permission changes reviewable (VCS), testable, and reproducible across environments.


## What it does

- Collects all files matching `*.permissions.yaml` from every active TYPO3 extension (recursive scan)
  - These YAML files basically contain abstractions of `be_groups` and `sys_filemounts` records.
- Merges all found configurations deterministically
- Writes/updates corresponding records in:
  - `sys_filemounts`
  - `be_groups`
- Resolves relations (e.g., `be_groups.subgroup`, `be_groups.file_mountpoints`) by referencing other items by their logical keys


## Goals

- Treat backend permissions as configuration-as-code
- Enable safe deployments without manual backend tweaking
- Keep environments (INT/PPE/PROD) in sync
- Provide a clear, readable schema that is easy to review in pull requests
- If you know how TYPO3 permissions work and how they are stored in the DB, you'll have no trouble writing the YAML abstractions  


## Installation

```bash
composer require uandi/ui-permissions
```

### Extension configuration (Settings → Extension Configuration)

- `pidBeGroups` (int): PID to store/update `be_groups` records
- `pidSysFilemounts` (int): PID to store/update `sys_filemounts` records
- `createFilemountDirectories` (bool): Create directories for filemounts if missing

These values are used by the repositories when inserting/updating records.


## CLI usage

Run via TYPO3 CLI. This command is ideally executed during deployments.

```bash
# Apply the current YAML permissions to the database
vendor/bin/typo3 ui_permissions:update
```


## Where to place YAML files

While the collector scans the whole extension path, the recommended location is:
- `EXT:your_ext/Configuration/Permissions/YourName.permissions.yaml` for general purposes
- `EXT:your_ext/ContentBlocks/ContentElements/*/permissions/YourName.permissions.yaml` for ContentBlock elements.

Any filename ending with `.permissions.yaml` is picked up.

### Naming conventions (permission files and keys)

Based on the “TYPO3 Backend User Management” concept (naming-for-purpose), use clear, prefixed keys. The map key of each item becomes its durable `permission_key` (and should also inform the filename):

- R_\<Name>: Role groups that aggregate capabilities via `subgroup`, e.g. `R_Editors`, `R_EditorsInChief`
- ACL_\<Name>: Access control scopes for single tables/features/CTypes, e.g. `ACL_tt_content_common`, `ACL_contentelement_gallery`
- FM_\<Name>: Filemount definitions and helper groups that attach filemounts, e.g. `FM_ProjeectIcons`
- DB_\<Name>: Database (page tree) mounts, e.g. `DB_Projeect`
- PG_\<Name>: Page-related presets, e.g. `PG_Projeect`
- L_\<Name>: Language-related presets for accessible languages, e.g. `L_Spanish`

#### Further reading

- https://archive-2019.typo3worx.eu/2017/02/typo3-backend-user-management/
- https://punkt.de/de/blog/2017/typo3-backend-berechtigungen.html

#### Recommendations

- Keep keys unique and stable; they act as IDs across environments
- Name files accordingly, e.g. `R_Editors.permissions.yaml` containing a `be_groups: R_Editors: ...` definition
- Compose roles (R_*) from ACL_* and FM_* using `subgroup` and `fileMountpoints`


## YAML schema
As was already noted, the YAML files are abstractions of the database records. So the top level keys describe which table we're dealing with:

- `sys_filemounts`: map of filemounts by a unique key ("permission key")
- `be_groups`: map of backend groups by a unique key ("permission key")

Each item’s map key becomes its `permission_key` in the database and is used to reference between items.

### sys_filemounts item fields

- `permission_key` (string, optional - map key will be used if missing)
- `title` (string, optional - map key will be used if missing)
- `description` (string, optional)
- `identifier` (string, e.g. `1:/assets`)
- `readOnly` (bool, default: false)

Legacy support: If `identifier` is missing, the pair `base` + `path` will be converted to `identifier` (`base:path`).

### be_groups item fields (selection)

- `permission_key` (string, optional - map key will be used if missing)
- `title` (string, optional - map key will be used if missing)
- `description` (string, optional)
- `tablesModify` (array|string CSV)
- `tablesSelect` (array|string CSV)
- `pagetypesSelect` (array|int|string CSV)
- `nonExcludeFields` (map `table: [field, ...]` or strings/CSV)
- `explicitAllowdeny` (map of tables → fields → [values])
  - Legacy support: `explicitAllowdeny: { allow: { table: { field: [values] } } }` also accepted
- `dbMountpoints` (array|int|string CSV of page IDs)
- `fileMountpoints` (array of filemount permission keys)
- `filePermissions` (array|string CSV)
- `subgroup` (array|string CSV of other be_group permission keys)
- `groupMods` (array|string CSV)
- `TSconfig` (string)
- `allowedLanguages` (array|string CSV of sys_language uids)
- `customOptions` (array|string CSV)
- `mfaProviders` (array)

Some fields are resolved after initial persistence (e.g., `subgroup`, `fileMountpoints`). See `Classes/Domain/Repository/BackendUserGroupRepository.php` for some insights.


### Merge behavior

Merging is custom (see `ConfigurationCollector::mergeConfiguration()`):

- Associative arrays are merged recursively; later values override earlier scalars
- List arrays are concatenated and `array_unique()`-ed

This enables layering configurations across multiple extensions.


## Examples
Examples of permissions files 

### Example ACL for a content element (CType)

```yaml
be_groups:
  ACL_contentelement_gallery:
    tables_select: tt_content
    tables_modify: tt_content
    explicit_allowdeny:
      tt_content:
        CType:
          - contentelement_gallery
```

### Example role composed of many ACLs

```yaml
be_groups:
  R_Editors:
    pagetypes_select: 1, 3, 4, 199, 254
    subgroup:
      - ACL_form_formframework
      - ACL_pages_common
      - ACL_sys_file_metadata
      - ACL_sys_file_reference
      - ACL_tt_content_common
      - ACL_tt_content_shortcut

      # Content Elements
      - ACL_contentelement_gallery
      - ACL_contentelement_media
      - ACL_contentelement_quote
      - ACL_contentelement_stage
      - ACL_contentelement_teaser
      - ACL_contentelement_textmedia

      # Grids
      - ACL_contentelement_grid2columns50-50
      - ACL_contentelement_grid3columns33-33-33
      - ACL_contentelement_grid4columns25-25-25-25
    groupMods:
      - web_layout
      - media_management
      - user_setup
```

### Example filemounts (FM_*) and their respective be_groups

```yaml
sys_filemounts:
  FM_ProjeectIcons:
    title: 'Assets'
    description: 'Base Fileadmin Folder for Icons'
    identifier: '1:/user_upload/Projeect/Icons/'

  FM_ProjeectIcons_ReadOnly:
    title: 'Icons'
    description: 'Base Fileadmin Folder for Icons (Read Only)'
    identifier: '1:/user_upload/Projeect/Icons/'
    read_only: 1

be_groups:
  FM_ProjeectIcons:
    file_mountpoints:
      - FM_ProjeectIcons

  FM_ProjeectIcons_ReadOnly:
    file_mountpoints:
      - FM_ProjeectIcons_ReadOnly
```

### Example database mount (DB_*)

```yaml
be_groups:
  DB_Projeect:
    db_mountpoints: 1
```


## Tips
 
- Keep permission keys unique and stable; they become the durable identifier (`permission_key`) across environments
- Split large setups into multiple files; merging will combine them
- Prefer arrays over CSV for readability, but both are supported in many fields


## What's next

- CLI Command to create YAML files from existing permissions in the database to make it easier to introduce this extension to existing projects
- Maybe try to introduce some level of plausibility/error checks to make it easier to find misconfigurations in the YAML abstraction files
 