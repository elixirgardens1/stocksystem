
# Table of Contents

1.  [Stock Control](#orgd3cae11)
    1.  [System Structure](#org4518996)
        1.  [Located](#org65ec4d3)
        2.  [Database](#org21e0b96)
        3.  [Dependencies](#org7a36b46)
    2.  [System Design](#org716d60d)
        1.  [Languages](#orgfffa6e0)


<a id="orgd3cae11"></a>

# Stock Control

The stock control system is a tool to track the products we are selling or have sold previously.


<a id="org4518996"></a>

## System Structure


<a id="org65ec4d3"></a>

### Located

<https://deepthought:8080/stocksystem:8080/dist/>


<a id="org21e0b96"></a>

### Database

X:\stocksystem\PHPAPI\stock<sub>control.db3</sub>


<a id="org7a36b46"></a>

### Dependencies

1.  Transparency

    The stock control is linked to @matrixCodes.db3 database located at Z:\FESP-REFACTOR\FespMVC\Modules\Transparanecy\matrixCodes.db3. Stock control has the Transparency page which is used for the management of protected asins sold on amazon. New Codes are also inserted from this page and various stats about the asins are available.


<a id="org716d60d"></a>

## System Design


<a id="orgfffa6e0"></a>

### Languages

1.  PHP

    PHP is used for retrieving and updating data in the backend databases, all of the php used within the project is located within the /PHPAPI folder located in the root of the stocksystem folder. Below I will discuss the purpose of all the php based files the system uses.
    
    1.  QueryController.php
    
        This processes all the GET requests from the Vue frontend, the axiosGet.js file you can see below passes the a $<sub>GET</sub> argument and the QueryController checks the argument passed and returns the relevant data.
        
            // Located: /src/composables/axiosGet.js
            import axios from "axios";
            
            export function axiosGet(type) {
              const promise = axios.get(
                `http://localhost/Ryan/Projects/stocksystem/PHPAPI/QueryController.php?${type}`
              );
            
              const dataPromise = promise.then((response) => response.data);
            
              return dataPromise;
            }
        
        The QueryController need to be improved by actually building a proper API controller for the basic queries that are common across all the pages on the system, for example, product information is used on the majority of pages so should be called once using a proper API call, rather than pulling everytime.
    
    2.  StockPost.php
    
        Similar in structure to the QueryController, processes POST requests from the Vue frontend, the axiosPost.js file you can see below takes the url arguments and POSTED data and updates the backend databases.

