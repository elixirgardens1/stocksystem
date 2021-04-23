
# Table of Contents

1.  [Stock Control](#orgd665a1a)
    1.  [System Structure](#org90ce0ba)
        1.  [Located](#org8fc6d3e)
        2.  [Database](#org7abec56)
        3.  [Dependencies](#org62c2af8)


<a id="orgd665a1a"></a>

# Stock Control

The stock control system is a tool to track the products we are selling or have sold previously.


<a id="org90ce0ba"></a>

## System Structure


<a id="org8fc6d3e"></a>

### Located

<https://deepthought:8080/stocksystem:8080/dist/>


<a id="org7abec56"></a>

### Database

X:\stocksystem\PHPAPI\stock<sub>control.db3</sub>


<a id="org62c2af8"></a>

### Dependencies

1.  Transparency

    The stock control is linked to @matrixCodes.db3 database located at Z:\FESP-REFACTOR\FespMVC\Modules\Transparanecy\matrixCodes.db3. Stock control has the Transparency page which is used for the management of protected asins sold on amazon. New Codes are also inserted from this page and various stats about the asins are available.

