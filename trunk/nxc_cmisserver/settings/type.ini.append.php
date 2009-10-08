<?php /*

[Folder]
typeId=folder
queryName=folder
baseType=folder
baseTypeQueryName=folder
description=Folder type
creatable=true
fileable=false
queryable=true
controllable=false
includedInSupertypeQuery=true

[Frontpage]
typeId=frontpage
queryName=frontpage
baseType=folder
baseTypeQueryName=folder
description=Frontpage type
creatable=true
fileable=false
queryable=true
controllable=false
includedInSupertypeQuery=true

[Image]
typeId=image
queryName=image
baseType=document
baseTypeQueryName=document
description=Image type
creatable=true
fileable=true
queryable=true
controllable=false
includedInSupertypeQuery=true
versionable=false
# A value that indicates whether a content-stream MAY, SHALL, or SHALL NOT be included in
# objects of this type. Values:
#     •   notallowed: A content-stream SHALL NOT be included
#     •   allowed: A content-stream MAY be included
#     •   required: A content-stream SHALL be included (i.e. SHALL be included when the object
#                   is created, and SHALL NOT be deleted.)
contentStreamAllowed=allowed
contentAttributeId=image

[File]
typeId=file
queryName=file
baseType=document
baseTypeQueryName=document
description=File type
creatable=true
fileable=true
queryable=true
controllable=false
includedInSupertypeQuery=true
versionable=false
contentStreamAllowed=allowed
contentAttributeId=file
# Alias for typeId.
# 'document' means the same with 'file' in this case
aliasList[]=document

*/ ?>