# FTP Deploy

**PHP-enhanced FTP deploy.** This tool will upload your files to
your web hosting over FTP(S). But instead of uploading files one by one,
which is notoriously slow over FTP, your files will be uploaded in a PHP
self-extracting archive script which will then be remotely executed.

The tool first uploads a helper script which scans the configured
root directory and creates a list of files on the remote side. Then it
compares that to a list of local files and compiles a list of actions
which are needed to make the remote file list match the local one.
From this the tool builds an archive file which contains all the necessary
data as well as the instructions to extract it. The archive is then uploaded
over FTP and executed on the remote server.

The tool assumes that you have a web hosting service accessible over
FTP(S) and HTTP, running PHP version 8.0 or newer. PHP on the server
must have write access to the files on the FTP host. You obviously also
need PHP 8 on the machine the deployment will be run from.

## Installation

FTP Deploy is available through Composer. You can install it locally
as a dev dependency of your project, or globally, as you prefer:

```shell
composer require jahudka/ftp-deploy
# or:
composer require --global jahudka/ftp-deploy
```

## Configuration

FTP Deploy is configured using a YAML file with the following format:

```yaml
host: ftp.host.com
port: 21
user: ftpuser
password: secret
localRoot: ./public
remoteRoot: /subdomains/www
publicDir: .
remoteRootRelativeToPublicDir: ~
baseUrl: http://site.com
files:
  - '!/static'
```

Except for `files`, all configuration options can also be set using
environment variables in the format `DEPLOY_*`, e.g. `DEPLOY_PUBLIC_DIR`.

 - `port` is optional and defaults to `21`.
 - `localRoot` can be relative; when specified in a config file, it is
   resolved relative to the config file, when specified using an environment
   variable, it is resolved relative to the current working directory.
 - `remoteRoot` is always converted to absolute; it is resolved from
   the root of the FTP directory.
 - `publicDir` is optional and defaults to `.`; if a relative path is
   specified, it is resolved relative to `remoteRoot`.
 - `remoteRootRelativeToPublicDir` is optional and by default, it is derived
   from the resolved `remoteRoot` and `publicDir` options; but in some slightly
   unusual scenarios where `publicDir` is a symlink, it might be impossible
   to resolve this correctly, so you can specify it authoritatively.
 - `baseUrl` must be the full absolute URL of `publicDir`.
 - `files` is a list of patterns by which files are selected on one or both
   sides of the operation. See below for details.

By default, `ftp-deploy` looks for a config file in the current working directory
and then searches up the file system tree until it reaches a directory which contains
a `composer.json` file, or the file system root. The accepted config file names are:
`.ftpdeployrc`, `.ftp-deploy.yml`, `.ftp-deploy.yaml`, `ftp-deploy.yml`, and
`ftp-deploy.yaml`. You can specify a custom config file using the `-c` or `--config`
command line option.

## File patterns

Formally, file patterns conform to the following grammar:

```
pattern := [<local|remote>:][!][path]
```

When building the file list, for each file the first matching pattern determines
whether the file will be included or excluded. If no pattern matches a file,
the file is included by default. If a directory is excluded, its contents will not
be traversed, so no pattern can match inside an excluded directory (similarly to `rsync`).

By default, patterns are used to match files on both sides of the transfer. By
prefixing a pattern with `local:` or `remote:`, we can restrict that pattern to
be used on one side only, which can be useful to e.g. exclude local cache files,
which would effectively result in purging the remote cache.

Patterns which start with a `!` at the position indicated above will cause matching
files to be excluded; otherwise patterns cause matching files to be included. Since
the default policy is to include files which don't match any pattern, inclusion
patterns will only be useful for files which would otherwise be excluded by a later
exclusion pattern.

Patterns whose `path` portion begins with a `/` are anchored to the root directory;
patterns without a leading `/` will match anywhere in the folder structure, provided
that their parent directories aren't excluded by another pattern.

## Deploy

The tool does its best to make a deployment as reversible and as atomic as possible
within the existing constraints. To that end, the deployment is executed in four
phases:
 1. Extraction:
   - new directories are created
   - permissions of existing files which aren't otherwise changed are updated
   - uploaded files are extracted to temporary files and backups of existing files
     are created
   - symlinks are created in a temporary location and backups of existing symlinks
     are created
 2. Commit:
   - extracted temporary files and temporary symlinks are moved to their final destination
 3. Removal:
   - files and directories scheduled for deletion are removed
 4. Cleanup:
   - any backup files and symlinks created during extraction are removed

If the deployment fails during step 1 or 2, the entire process is reverted:
 - Any files and symlinks which have been overwritten are restored from backups
 - Any directories which have been created are removed
 - Any files or directories whose permissions have been changed are reset
   to their original state
 - Any temporary files and unused backups are removed

Failures during removal and cleanup are logged, but do not cause the deployment
to be reverted.

## Situations which FTP Deploy can handle

 - remote file doesn't exist -> upload
 - remote file has different content -> upload
 - remote file has different permissions -> chmod
 - remote directory doesn't exist -> mkdir
 - remote directory has different permissions -> chmod
 - local file doesn't exist -> unlink
 - local directory doesn't exist -> rmdir

## Situations which FTP Deploy cannot handle

 - remote file type is different from local (e.g. directory vs. regular file,
   symlink vs. non-symlink etc) - but at least it's detected early so we don't
   do anything when this happens
